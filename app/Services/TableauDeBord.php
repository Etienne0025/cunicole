<?php

namespace App\Services;

use App\Enums\SexeAnimal;
use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Models\ActionSynchronisee;
use App\Models\Cage;
use App\Models\LotEngraissement;
use App\Models\ObjectifNaissance;
use App\Models\Portee;
use App\Models\ProduitVeterinaire;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Traitement;
use App\Support\Monnaie;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TableauDeBord
{
    public function __construct(
        private SuiviMortalite $mortalite,
        private GestionAliments $aliments,
        private GestionTaches $taches,
        private PlanningElevage $planning,
        private CalendrierVaccinal $calendrier,
        private BilanFinancier $bilan,
    ) {}

    public function donnees(): array
    {
        // Sans doublon : garantit une checklist à jour même si le planificateur ne tourne pas
        $this->taches->synchroniser();

        return [
            'cages' => $this->cages(),
            'cheptel' => $this->cheptel(),
            'reproduction' => $this->reproduction(),
            'sante' => $this->sante(),
            'alimentation' => $this->alimentation(),
            'finances' => $this->financesDuMois(),
            'journee' => $this->journee(),
            'synchronisation' => $this->synchronisation(),
        ];
    }

    /** Alertes classées par gravité : critique, avertissement, information. */
    public function alertes(array $d): Collection
    {
        $liste = collect();

        $mortalite = $d['sante']['mortalite'];

        if ($mortalite['depasse']) {
            $liste->push([
                'code' => 'mortalite',
                'niveau' => 'critique',
                'message' => 'Taux de mortalité de '.number_format($mortalite['taux'], 1, ',', ' ')." % sur {$mortalite['jours']} jours (seuil critique : {$mortalite['seuil']} %).",
                'lien' => route('proprietaire.sante'),
            ]);
        }

        foreach ($d['alimentation']['alertes'] as $alerte) {
            $epuise = $alerte['autonomie'] !== null && $alerte['autonomie'] <= 0;

            $liste->push([
                'code' => 'stock_aliment',
                'niveau' => $epuise ? 'critique' : 'avertissement',
                'message' => $epuise
                    ? "Stock épuisé : {$alerte['aliment']->nom}."
                    : "Stock faible : {$alerte['aliment']->nom} (".number_format($alerte['autonomie'], 1, ',', ' ')." jour(s) d'autonomie).",
                'lien' => route('proprietaire.aliments'),
            ]);
        }

        foreach ($d['sante']['produits_bas'] as $produit) {
            $liste->push([
                'code' => 'stock_pharmacie',
                'niveau' => 'avertissement',
                'message' => "Stock bas à la pharmacie : {$produit->nom} (".number_format($produit->stock_actuel, 2, ',', ' ')." {$produit->unite} restant(s)).",
                'lien' => route('proprietaire.pharmacie'),
            ]);
        }

        $echec = $d['reproduction']['echec'];

        if ($echec['alerte']) {
            $liste->push([
                'code' => 'echec_saillies',
                'niveau' => 'avertissement',
                'message' => "Taux d'échec des saillies élevé : ".number_format($echec['taux'], 1, ',', ' ')." % sur 90 jours (seuil : {$echec['seuil']} %).",
                'lien' => route('proprietaire.reproduction'),
            ]);
        }

        $nonCloturees = $d['journee']['non_cloturees'];

        if ($nonCloturees->isNotEmpty()) {
            $liste->push([
                'code' => 'journees',
                'niveau' => 'avertissement',
                'message' => $nonCloturees->count().' journée(s) non clôturée(s) par le gestionnaire (la plus ancienne : '.Carbon::parse($nonCloturees->first())->format('d/m/Y').').',
                'lien' => route('proprietaire.rapports.index'),
            ]);
        }

        if ($d['synchronisation']['refusees'] > 0) {
            $liste->push([
                'code' => 'sync_refusees',
                'niveau' => 'avertissement',
                'message' => "{$d['synchronisation']['refusees']} saisie(s) faite(s) hors ligne ont été refusées et n'ont pas été prises en compte (7 derniers jours).",
                'lien' => route('proprietaire.synchronisations'),
            ]);
        }

        if ($d['cheptel']['en_attente'] > 0) {
            $liste->push([
                'code' => 'validation',
                'niveau' => 'information',
                'message' => "{$d['cheptel']['en_attente']} nouveau(x) reproducteur(s) en attente de votre validation.",
                'lien' => route('proprietaire.selection'),
            ]);
        }

        if ($d['sante']['vaccins_dus'] > 0) {
            $liste->push([
                'code' => 'vaccins',
                'niveau' => 'information',
                'message' => "{$d['sante']['vaccins_dus']} vaccination(s) à effectuer selon le calendrier prophylactique.",
                'lien' => route('proprietaire.sante'),
            ]);
        }

        if ($d['finances']['creances'] > 0) {
            $liste->push([
                'code' => 'creances',
                'niveau' => 'information',
                'message' => 'Créances à recouvrer : '.Monnaie::format($d['finances']['creances']).'.',
                'lien' => route('proprietaire.ventes.index', ['statut' => 'impayees', 'periode' => 'annee']),
            ]);
        }

        $ordre = ['critique' => 0, 'avertissement' => 1, 'information' => 2];

        return $liste->sortBy(fn (array $alerte) => $ordre[$alerte['niveau']])->values();
    }

    // ------------------------------------------------------------------

    private function cages(): array
    {
        $parStatut = Cage::whereNotNull('activee_le')
            ->selectRaw('statut, count(*) as n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $activees = (int) $parStatut->sum();
        $occupees = (int) ($parStatut[StatutCage::Occupee->value] ?? 0);

        return [
            'total' => Cage::count(),
            'activees' => $activees,
            'occupees' => $occupees,
            'vides' => (int) ($parStatut[StatutCage::Vide->value] ?? 0),
            'a_nettoyer' => (int) ($parStatut[StatutCage::ANettoyer->value] ?? 0),
            'taux' => $activees > 0 ? (int) round($occupees / $activees * 100) : null,
        ];
    }

    private function cheptel(): array
    {
        $actifs = fn () => Reproducteur::where('statut', StatutReproducteur::Actif->value);
        $lotsEnCours = fn () => LotEngraissement::where('statut', StatutLot::EnCours->value);

        return [
            'femelles' => $actifs()->whereNotNull('valide_le')->where('sexe', SexeAnimal::Femelle->value)->count(),
            'males' => $actifs()->whereNotNull('valide_le')->where('sexe', SexeAnimal::Male->value)->count(),
            'en_attente' => $actifs()->whereNull('valide_le')->count(),
            'sans_cage' => $actifs()->whereNull('cage_id')->count(),
            'lots' => $lotsEnCours()->count(),
            'lapins_engraissement' => (int) $lotsEnCours()->sum('effectif_actuel'),
        ];
    }

    private function reproduction(): array
    {
        $objectif = ObjectifNaissance::where('annee', today()->year)->where('mois', today()->month)->value('objectif');

        $naissances = (int) Portee::whereBetween('date_mise_bas', [
            today()->startOfMonth()->toDateString(),
            today()->endOfMonth()->toDateString(),
        ])->sum('nes_vivants');

        return [
            'en_cours' => Saillie::whereIn('statut', StatutSaillie::valeursEnCours())->count(),
            'gestantes' => Saillie::where('statut', StatutSaillie::Gestante->value)->count(),
            'mises_bas_attendues' => $this->planning->misesBasAttendues(7)->count(),
            'naissances' => $naissances,
            'objectif' => $objectif !== null ? (int) $objectif : null,
            'pourcentage_objectif' => ($objectif !== null && (int) $objectif > 0)
                ? (int) round($naissances / (int) $objectif * 100)
                : null,
            'echec' => $this->planning->tauxEchecSaillies(),
        ];
    }

    private function sante(): array
    {
        $aujourdhui = today()->toDateString();

        $bloques = Traitement::where('date_debut', '<=', $aujourdhui)
            ->where('date_fin_attente', '>=', $aujourdhui)
            ->get()
            ->unique(fn (Traitement $t) => $t->traitable_type.'-'.$t->traitable_id)
            ->count();

        return [
            'mortalite' => $this->mortalite->taux(),
            'bloques' => $bloques,
            'vaccins_dus' => $this->calendrier->echeances()->count(),
            'produits_bas' => ProduitVeterinaire::where('actif', true)->get()
                ->filter(fn (ProduitVeterinaire $p) => $p->stockBas())
                ->values(),
        ];
    }

    private function alimentation(): array
    {
        $etats = $this->aliments->etatStocks()->filter(fn (array $etat) => $etat['aliment']->actif)->values();

        return [
            'aliments' => $etats->count(),
            'alertes' => $etats->filter(fn (array $etat) => $etat['bas'])->values(),
            'autonomie_min' => $etats->min('autonomie'),
        ];
    }

    private function financesDuMois(): array
    {
        $bilan = $this->bilan->periode(today()->startOfMonth(), today()->endOfMonth());

        return [
            'ca' => $bilan['ca'],
            'charges' => $bilan['charges']['total'],
            'benefice' => $bilan['benefice'],
            'marge' => $bilan['marge'],
            'creances' => $this->bilan->creances(),
            'objectifs' => $this->bilan->progressionObjectifs(),
            'serie' => $this->bilan->serieMensuelle(6),
        ];
    }

    private function journee(): array
    {
        return [
            'progression' => $this->taches->progression(today()),
            'cloturee' => $this->taches->estCloturee(today()),
            'non_cloturees' => $this->taches->joursNonClotures(),
        ];
    }

    private function synchronisation(): array
    {
        return [
            'refusees' => ActionSynchronisee::where('statut', 'refusee')
                ->where('traitee_le', '>=', now()->subDays(7))
                ->count(),
        ];
    }
}