<?php

namespace App\Services;
use App\Models\Distribution;
use App\Models\Ration;

use App\Models\Reproducteur;
use App\Models\Traitement;
use App\Models\Vaccination;
use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutSaillie;
use App\Enums\StatutTache;
use App\Enums\TypeTache;
use App\Models\Cage;
use App\Models\ClotureJournee;
use App\Models\LotEngraissement;
use App\Models\Tache;
use App\Models\Utilisateur;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionTaches
{
   private ?array $effectifsMemo = null;

public function __construct(
    private PlanningElevage $planning,
    private CalendrierVaccinal $calendrier,
    private EffectifsAlimentation $effectifs,
) {}

    // ------------------------------------------------------------------
    // Génération
    // ------------------------------------------------------------------

    /** Crée les tâches manquantes du jour (sans doublon). Retourne le nombre de tâches créées. */
    public function synchroniser(?CarbonInterface $jour = null): int
    {
        $jour = ($jour ?? today())->copy()->startOfDay();

        if ($this->estCloturee($jour)) {
            return 0;
        }

        $lignes = $this->tachesElevage($jour)
    ->concat($this->tachesCages($jour))
    ->concat($this->tachesLots($jour))
    ->concat($this->tachesSante($jour))
     ->concat($this->tachesAlimentation($jour))
    ->concat($this->tachesQuotidiennes($jour));
        $avant = Tache::whereDate('date_prevue', $jour->toDateString())->count();

        if ($lignes->isNotEmpty()) {
            // La contrainte d'unicité sur cle_unique ignore les doublons, même en cas d'accès simultanés
            Tache::insertOrIgnore($lignes->all());
        }

        $creees = Tache::whereDate('date_prevue', $jour->toDateString())->count() - $avant;

        $this->resoudreOuvertes();

        return $creees;
    }

    private function tachesElevage(CarbonInterface $jour): Collection
    {
        $planning = $this->planning->pour($jour);
        $lignes = collect();

        $cage = fn ($saillie) => $saillie->femelle->cage ? 'Cage '.$saillie->femelle->cage->code.' · ' : '';

        foreach ($planning['palpations'] as $saillie) {
            $lignes->push($this->ligne($jour, TypeTache::Palpation, (string) $saillie->id,
                "Palpation de {$saillie->femelle->matricule}",
                $cage($saillie).'saillie du '.$saillie->date_saillie->format('d/m/Y').' avec '.$saillie->male->matricule,
                $saillie));
        }

        foreach ($planning['boites_nids'] as $saillie) {
            $lignes->push($this->ligne($jour, TypeTache::BoiteNids, (string) $saillie->id,
                "Installer la boîte à nids de {$saillie->femelle->matricule}",
                $cage($saillie).'saillie du '.$saillie->date_saillie->format('d/m/Y'),
                $saillie));
        }

        foreach ($planning['mises_bas'] as $saillie) {
            $lignes->push($this->ligne($jour, TypeTache::SurveillanceMiseBas, (string) $saillie->id,
                "Surveiller la mise bas de {$saillie->femelle->matricule}",
                $cage($saillie).'mise bas prévue le '.$saillie->datePrevue('mise_bas')->format('d/m/Y'),
                $saillie));
        }

        foreach ($planning['sevrages'] as $saillie) {
            $lignes->push($this->ligne($jour, TypeTache::Sevrage, (string) $saillie->id,
                "Sevrer la portée de {$saillie->femelle->matricule}",
                'mise bas du '.$saillie->portee?->date_mise_bas->format('d/m/Y'),
                $saillie));
        }

        return $lignes;
    }

    private function tachesCages(CarbonInterface $jour): Collection
    {
        return Cage::with('batiment')
            ->where('statut', StatutCage::ANettoyer->value)
            ->get()
            ->map(fn (Cage $cage) => $this->ligne($jour, TypeTache::NettoyageCage, (string) $cage->id,
                "Nettoyer la cage {$cage->code}", $cage->batiment->nom, $cage));
    }

    private function tachesLots(CarbonInterface $jour): Collection
    {
        $limite = $jour->copy()->subDays(config('cunicole.taches.jours_entre_pesees_lot', 7));

        return LotEngraissement::with('dernierePesee')
            ->where('statut', StatutLot::EnCours->value)
            ->whereDate('date_debut', '<=', $limite->toDateString())
            ->get()
            ->filter(fn (LotEngraissement $lot) => $lot->dernierePesee === null
                || $lot->dernierePesee->date_pesee->lte($limite))
            ->map(fn (LotEngraissement $lot) => $this->ligne($jour, TypeTache::PeseeLot, (string) $lot->id,
                "Peser le lot {$lot->code}",
                $lot->dernierePesee
                    ? 'Dernière pesée le '.$lot->dernierePesee->date_pesee->format('d/m/Y')
                    : 'Aucune pesée depuis la mise en lot',
                $lot));
    }

    private function tachesQuotidiennes(CarbonInterface $jour): Collection
    {
        return collect(config('cunicole.taches.quotidiennes', []))
            ->map(fn (string $titre) => $this->ligne($jour, TypeTache::Quotidienne, Str::slug($titre), $titre));
    }

   private function ligne(
    CarbonInterface $jour,
    TypeTache $type,
    string $cle,
    string $titre,
    ?string $description = null,
    ?Model $source = null,
    ?int $protocoleId = null,
): array {
    $maintenant = now();

    return [
        'cle_unique' => "{$type->value}:{$cle}:{$jour->toDateString()}",
        'type' => $type->value,
        'date_prevue' => $jour->toDateString(),
        'titre' => Str::limit($titre, 200, ''),
        'description' => $description ? Str::limit($description, 250, '') : null,
        'source_type' => $source?->getMorphClass(),
        'source_id' => $source?->getKey(),
        'protocole_id' => $protocoleId,
        'statut' => StatutTache::AFaire->value,
        'automatique' => false,
        'created_at' => $maintenant,
        'updated_at' => $maintenant,
    ];
}

    // ------------------------------------------------------------------
    // Résolution automatique
    // ------------------------------------------------------------------

    /** Appelée quand un élément change (saillie, cage, lot, pesée) : termine les tâches devenues sans objet. */
    public function resoudre(Model $source): void
    {
        Tache::where('statut', StatutTache::AFaire->value)
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->get()
            ->each(function (Tache $tache) use ($source) {
                $tache->setRelation('source', $source);
                $this->tenterResolution($tache);
            });
    }

    /** Filet de sécurité : repasse sur toutes les tâches ouvertes. */
    public function resoudreOuvertes(): void
    {
        Tache::with('source')
            ->where('statut', StatutTache::AFaire->value)
            ->whereNotNull('source_type')
            ->get()
            ->each(fn (Tache $tache) => $this->tenterResolution($tache));
    }

   private function tenterResolution(Tache $tache): void
{
    $source = $tache->source;

    if ($source === null) {
        $tache->update(['statut' => StatutTache::Annulee, 'justification' => 'Élément supprimé']);

        return;
    }

    if ($this->obsolete($tache, $source)) {
        $tache->update(['statut' => StatutTache::Annulee, 'justification' => 'Animal sorti du cheptel ou élément clôturé']);

        return;
    }

    if ($this->conditionRemplie($tache, $source)) {
        $tache->update([
            'statut' => StatutTache::Faite,
            'faite_le' => $this->moment(),
            'automatique' => true,
        ]);
    }
}

/** Heure de la saisie en cours (mode hors ligne), sinon maintenant. */
private function moment(): CarbonInterface
{
    return app()->bound('moment.operation') ? app('moment.operation') : now();
}

   private function conditionRemplie(Tache $tache, Model $source): bool
{
    return match ($tache->type) {
        TypeTache::Palpation => $source->statut !== StatutSaillie::EnAttente,
        TypeTache::BoiteNids => $source->boite_nids_le !== null || $source->statut !== StatutSaillie::Gestante,
        TypeTache::SurveillanceMiseBas => $source->statut !== StatutSaillie::Gestante,
        TypeTache::Sevrage => $source->statut !== StatutSaillie::MiseBas,
        TypeTache::NettoyageCage => $source->statut !== StatutCage::ANettoyer,
        TypeTache::PeseeLot => $source->statut !== StatutLot::EnCours
            || $source->pesees()->whereDate('date_pesee', '>=', $tache->date_prevue->toDateString())->exists(),
        // Satisfaite dès qu'une vaccination de ce protocole est enregistrée après la création de la tâche
        TypeTache::Vaccination => Vaccination::where('vaccinable_type', $source->getMorphClass())
            ->where('vaccinable_id', $source->getKey())
            ->where('protocole_id', $tache->protocole_id)
            ->where('created_at', '>=', $tache->created_at)
            ->exists(),
        // Les traitements se cochent à la main chaque jour
        TypeTache::Traitement => false,
        TypeTache::Quotidienne => false,

        TypeTache::Distribution => Distribution::where('ration_id', $source->getKey())
    ->whereDate('date_distribution', $tache->date_prevue->toDateString())
    ->exists(),
    };
}

    // ------------------------------------------------------------------
    // Actions du gestionnaire
    // ------------------------------------------------------------------

   public function cocher(Tache $tache, Utilisateur $par, ?CarbonInterface $moment = null): void
{
    $moment ??= now();

    if ($tache->statut !== StatutTache::AFaire) {
        $this->refuser('action', 'Cette tâche est déjà traitée.');
    }
    if (! $tache->type->cochableManuellement()) {
        $this->refuser('action', "Cette tâche se termine automatiquement quand l'action est enregistrée. Utilisez le bouton « Ouvrir ».");
    }
    if (! $tache->date_prevue->isSameDay($moment)) {
        $this->refuser('action', 'Seules les tâches du jour peuvent être cochées. Clôturez la journée concernée en justifiant les tâches restantes.');
    }
    if ($this->estCloturee($tache->date_prevue)) {
        $this->refuser('action', 'Cette journée est clôturée.');
    }

    $tache->update([
        'statut' => StatutTache::Faite,
        'faite_le' => $moment,
        'faite_par_id' => $par->id,
        'automatique' => false,
    ]);
}

    public function decocher(Tache $tache): void
    {
        if ($tache->statut !== StatutTache::Faite || $tache->automatique) {
            $this->refuser('action', 'Seule une tâche cochée à la main peut être annulée.');
        }
        if ($this->estCloturee($tache->date_prevue)) {
            $this->refuser('action', 'Cette journée est clôturée.');
        }

        $tache->update(['statut' => StatutTache::AFaire, 'faite_le' => null, 'faite_par_id' => null]);
    }

    /**
     * @param  array<int|string, string|null>  $justifications  [id de la tâche => justification]
     */
    public function cloturer(CarbonInterface $jour, array $justifications, ?string $commentaire, Utilisateur $par): ClotureJournee
    {
        $jour = $jour->copy()->startOfDay();

        if ($jour->isFuture()) {
            $this->refuser('date', 'Impossible de clôturer une journée à venir.');
        }
        if ($this->estCloturee($jour)) {
            $this->refuser('date', 'Cette journée est déjà clôturée.');
        }

        return DB::transaction(function () use ($jour, $justifications, $commentaire, $par) {
            if ($jour->isToday()) {
                $this->synchroniser($jour);
            }

            $ouvertes = Tache::whereDate('date_prevue', $jour->toDateString())
                ->where('statut', StatutTache::AFaire->value)
                ->lockForUpdate()
                ->get();

            $erreurs = [];
            foreach ($ouvertes as $tache) {
                if (mb_strlen(trim((string) ($justifications[$tache->id] ?? ''))) < 5) {
                    $erreurs["justifications.{$tache->id}"] = "Justifiez la tâche « {$tache->titre} » (5 caractères minimum).";
                }
            }
            if ($erreurs) {
                throw ValidationException::withMessages($erreurs);
            }

            foreach ($ouvertes as $tache) {
                $tache->update([
                    'statut' => StatutTache::NonFaite,
                    'justification' => trim($justifications[$tache->id]),
                ]);
            }

            $bilan = $this->progression($jour);

            return ClotureJournee::create([
                'date' => $jour,
                'utilisateur_id' => $par->id,
                'cloturee_le' => now(),
                'total' => $bilan['total'],
                'faites' => $bilan['faites'],
                'non_faites' => $bilan['non_faites'],
                'commentaire' => $commentaire ?: null,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    public function estCloturee(CarbonInterface $jour): bool
    {
        return ClotureJournee::whereDate('date', $jour->toDateString())->exists();
    }

    /** @return Collection<int, string> dates (Y-m-d) passées dont des tâches restent « à faire » */
    public function joursNonClotures(): Collection
    {
        return Tache::where('statut', StatutTache::AFaire->value)
            ->whereDate('date_prevue', '<', today()->toDateString())
            ->get()
            ->map(fn (Tache $tache) => $tache->date_prevue->toDateString())
            ->unique()
            ->sort()
            ->values();
    }

    public function tachesDuJour(CarbonInterface $jour): Collection
    {
        return Tache::with(['source', 'faitePar'])
            ->whereDate('date_prevue', $jour->toDateString())
            ->where('statut', '!=', StatutTache::Annulee->value)
            ->get()
            ->sortBy(fn (Tache $tache) => sprintf('%d-%d-%06d', $tache->statut->ordre(), $tache->type->ordre(), $tache->id))
            ->values();
    }

    /** @return array{total: int, faites: int, non_faites: int, restantes: int, pourcentage: int|null} */
    public function progression(CarbonInterface $jour): array
    {
        $compte = Tache::whereDate('date_prevue', $jour->toDateString())
            ->where('statut', '!=', StatutTache::Annulee->value)
            ->selectRaw('statut, count(*) as n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $faites = (int) ($compte[StatutTache::Faite->value] ?? 0);
        $nonFaites = (int) ($compte[StatutTache::NonFaite->value] ?? 0);
        $restantes = (int) ($compte[StatutTache::AFaire->value] ?? 0);
        $total = $faites + $nonFaites + $restantes;

        return [
            'total' => $total,
            'faites' => $faites,
            'non_faites' => $nonFaites,
            'restantes' => $restantes,
            'pourcentage' => $total > 0 ? (int) round($faites / $total * 100) : null,
        ];
    }

    /** État complet d'une journée, utilisé par l'écran de supervision du propriétaire (JSON). */
    public function etat(CarbonInterface $jour): array
    {
        return [
            'date' => $jour->translatedFormat('l d F Y'),
            'cloturee' => $this->estCloturee($jour),
            ...$this->progression($jour),
            'actualise_a' => now()->format('H:i:s'),
            'taches' => $this->tachesDuJour($jour)->map(fn (Tache $tache) => [
                'id' => $tache->id,
                'titre' => $tache->titre,
                'description' => $tache->description,
                'type' => $tache->type->libelle(),
                'statut' => $tache->statut->value,
                'statut_libelle' => $tache->statut->libelle(),
                'heure' => $tache->faite_le?->format('H:i'),
                'par' => $tache->faitePar?->nom_complet ?? ($tache->automatique ? 'Automatique' : null),
                'justification' => $tache->justification,
            ])->all(),
        ];
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }

    private function tachesSante(CarbonInterface $jour): Collection
{
    $lignes = collect();

    foreach ($this->calendrier->echeances($jour) as $echeance) {
        $cible = $echeance['cible'];
        $protocole = $echeance['protocole'];
        $nom = $cible instanceof Reproducteur ? $cible->matricule : $cible->code;

        $lignes->push($this->ligne(
            $jour,
            TypeTache::Vaccination,
            "{$protocole->id}-{$cible->getMorphClass()}-{$cible->getKey()}",
            "Vacciner {$nom} : {$protocole->nom}",
            $echeance['motif'],
            $cible,
            $protocole->id,
        ));
    }

    $jourSql = $jour->toDateString();

    Traitement::with('traitable')
        ->whereDate('date_debut', '<=', $jourSql)
        ->whereDate('date_fin_traitement', '>=', $jourSql)
        ->get()
        ->filter(fn (Traitement $t) => $this->estActif($t->traitable))
        ->each(function (Traitement $t) use ($lignes, $jour) {
            $numero = (int) $t->date_debut->diffInDays($jour) + 1;

            $lignes->push($this->ligne(
                $jour,
                TypeTache::Traitement,
                (string) $t->id,
                "Traitement de {$t->libelleCible()} : {$t->nom_traitement}",
                "Jour {$numero} sur {$t->duree_jours} · {$t->symptomes}",
                $t,
            ));
        });

    return $lignes;
}

private function estActif(?Model $cible): bool
{
    return match (true) {
        $cible instanceof Reproducteur => $cible->estActif(),
        $cible instanceof LotEngraissement => $cible->statut === StatutLot::EnCours,
        default => false,
    };
}
  

/** La tâche n'a plus d'objet : on l'annule au lieu de la cocher comme « faite ». */
private function obsolete(Tache $tache, Model $source): bool
{
    if ($tache->type === TypeTache::Vaccination) {
        $protocole = $tache->protocole;

        if ($protocole === null || ! $protocole->actif) {
            return true;
        }
    }

    $cible = $source instanceof Traitement ? $source->traitable : $source;

    return match (true) {
        $cible instanceof Saillie => $cible->statut === StatutSaillie::Interrompue,
        $cible instanceof Reproducteur => ! $cible->estActif(),
        $cible instanceof LotEngraissement => $cible->statut !== StatutLot::EnCours,
        $cible instanceof Ration => $this->rationObsolete($tache, $cible),
        default => false,
    };
}

private function rationObsolete(Tache $tache, Ration $ration): bool
{
    $ration->loadMissing('aliment');

    if (! $ration->actif || ! $ration->aliment?->actif) {
        return true;
    }

    // Une tâche d'un jour passé reste à justifier à la clôture, même si la catégorie est vide aujourd'hui
    return $tache->date_prevue->isToday()
        && ($this->effectifsActuels()[$ration->categorie->value] ?? 0) === 0;
}


private function tachesAlimentation(CarbonInterface $jour): Collection
{
    // Les effectifs sont ceux d'aujourd'hui : on ne génère pas de distribution pour un autre jour
    if (! $jour->isToday()) {
        return collect();
    }

    $effectifs = $this->effectifsActuels();

    return Ration::with('aliment')
        ->where('actif', true)
        ->whereHas('aliment', fn ($q) => $q->where('actif', true))
        ->get()
        ->filter(fn (Ration $ration) => ($effectifs[$ration->categorie->value] ?? 0) > 0)
        ->sortBy(fn (Ration $ration) => $ration->categorie->ordre())
        ->map(fn (Ration $ration) => $this->ligne(
            $jour,
            TypeTache::Distribution,
            (string) $ration->id,
            "Distribuer {$ration->aliment->nom} : {$ration->categorie->libelle()}",
            $effectifs[$ration->categorie->value].' animal(aux) · '.$ration->grammes_par_jour.' g '.$ration->categorie->parUnite(),
            $ration,
        ))
        ->values();
}

/** @return array<string, int> */
private function effectifsActuels(): array
{
    return $this->effectifsMemo ??= $this->effectifs->parCategorie();
}


}