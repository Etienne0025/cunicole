<?php

namespace App\Services;

use App\Enums\StatutLot;
use App\Enums\StatutTache;
use App\Enums\TypeTache;
use App\Models\ClotureJournee;
use App\Models\LotEngraissement;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use App\Models\Reproducteur;
use App\Models\Tache;
use App\Models\Traitement;
use App\Models\Utilisateur;
use App\Models\Vaccination;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SoinsVeterinaires
{
    public function __construct(private Pharmacie $pharmacie) {}

    /** « Vaccin fait » : enregistre la vaccination, décompte le stock et termine la tâche. */
   /** « Vaccin fait » : enregistre la vaccination, décompte le stock et termine la tâche. */
public function vaccinerDepuisTache(Tache $tache, Utilisateur $par, ?CarbonInterface $moment = null): Vaccination
{
    $moment ??= now();

    if ($tache->type !== TypeTache::Vaccination || $tache->statut !== StatutTache::AFaire) {
        $this->refuser('action', "Cette vaccination n'est plus à faire.");
    }
    if (! $tache->date_prevue->isSameDay($moment)) {
        $this->refuser('action', 'Seules les tâches du jour peuvent être validées. Clôturez la journée concernée en justifiant les tâches restantes.');
    }
    if (ClotureJournee::whereDate('date', $tache->date_prevue->toDateString())->exists()) {
        $this->refuser('action', 'Cette journée est clôturée.');
    }

    $protocole = $tache->protocole()->with('produit')->first();
    $cible = $tache->source;

    if ($protocole === null || ! $protocole->actif || $cible === null || ! $this->estActif($cible)) {
        $this->refuser('action', "Ce vaccin n'est plus nécessaire (protocole désactivé ou animal sorti).");
    }

    return DB::transaction(function () use ($tache, $protocole, $cible, $par, $moment) {
        $nombre = $cible instanceof LotEngraissement ? $cible->effectif_actuel : 1;
        $quantite = round((float) $protocole->doses_par_animal * $nombre, 2);

        $this->pharmacie->consommer(
            $protocole->produit,
            $quantite,
            $par,
            "Vaccination {$this->libelle($cible)} ({$protocole->nom})",
            $moment,
        );

        $vaccination = $cible->vaccinations()->create([
            'protocole_id' => $protocole->id,
            'produit_id' => $protocole->produit_id,
            'nom_vaccin' => $protocole->produit->nom,
            'date_vaccination' => $moment->copy()->startOfDay(),
            'quantite_utilisee' => $quantite,
            'anterieure' => false,
            'utilisateur_id' => $par->id,
        ]);

        // L'événement « Vaccination créée » a déjà terminé la tâche : on y ajoute l'auteur
        $tache->refresh();
        $tache->update([
            'statut' => StatutTache::Faite,
            'faite_le' => $tache->faite_le ?? $moment,
            'faite_par_id' => $par->id,
            'automatique' => true,
        ]);

        return $vaccination;
    });
}
    /** Vaccination faite avant l'utilisation de l'application : aucun stock n'est décompté. */
    public function vaccinationAnterieure(Model $cible, ProtocoleVaccinal $protocole, CarbonInterface $date, Utilisateur $par): Vaccination
    {
        if (! $this->estActif($cible)) {
            $this->refuser('action', "Cet animal ou ce lot n'est plus dans l'élevage.");
        }
        if (! $protocole->actif || $protocole->cible->value !== $cible->getMorphClass()) {
            $this->refuser('protocole_id', "Ce protocole ne s'applique pas à cet animal ou à ce lot.");
        }

        $naissance = $cible instanceof LotEngraissement ? $cible->portee?->date_mise_bas : $cible->date_naissance;

        if ($naissance && $date->lt($naissance)) {
            $this->refuser('date_vaccination', 'La vaccination ne peut pas précéder la naissance.');
        }

        $protocole->loadMissing('produit');

        return $cible->vaccinations()->create([
            'protocole_id' => $protocole->id,
            'produit_id' => $protocole->produit_id,
            'nom_vaccin' => $protocole->produit->nom,
            'date_vaccination' => $date,
            'quantite_utilisee' => 0,
            'anterieure' => true,
            'utilisateur_id' => $par->id,
        ]);
    }

    /**
     * @param  array{produit_id?: ?int, nom_traitement?: ?string, symptomes: string, date_debut: string, duree_jours: int|string, delai_attente_jours: int|string, quantite?: float|string|null}  $donnees
     */
    public function traiter(Model $cible, array $donnees, Utilisateur $par): Traitement
    {
        if (! $this->estActif($cible)) {
            $this->refuser('action', "Cet animal ou ce lot n'est plus dans l'élevage.");
        }

        $debut = Carbon::parse($donnees['date_debut'])->startOfDay();
        $entree = $cible instanceof LotEngraissement ? $cible->date_debut : $cible->date_entree;

        if ($debut->lt($entree)) {
            $this->refuser('date_debut', "Le traitement ne peut pas précéder l'entrée de l'animal ou du lot.");
        }

        $produit = ! empty($donnees['produit_id']) ? ProduitVeterinaire::findOrFail($donnees['produit_id']) : null;
        $duree = (int) $donnees['duree_jours'];
        $delai = (int) $donnees['delai_attente_jours'];

        $finTraitement = $debut->copy()->addDays($duree - 1);
        $finAttente = $finTraitement->copy()->addDays($delai);

        return DB::transaction(function () use ($cible, $donnees, $par, $produit, $debut, $duree, $delai, $finTraitement, $finAttente) {
            if ($produit !== null) {
                $this->pharmacie->consommer($produit, (float) $donnees['quantite'], $par, "Traitement de {$this->libelle($cible)}");
            }

            return $cible->traitements()->create([
                'produit_id' => $produit?->id,
                'nom_traitement' => $produit?->nom ?? $donnees['nom_traitement'],
                'symptomes' => $donnees['symptomes'],
                'date_debut' => $debut,
                'duree_jours' => $duree,
                'delai_attente_jours' => $delai,
                'date_fin_traitement' => $finTraitement,
                'date_fin_attente' => $finAttente,
                'quantite_utilisee' => $produit ? $donnees['quantite'] : null,
                'utilisateur_id' => $par->id,
            ]);
        });
    }

    private function estActif(Model $cible): bool
    {
        return match (true) {
            $cible instanceof Reproducteur => $cible->estActif(),
            $cible instanceof LotEngraissement => $cible->statut === StatutLot::EnCours,
            default => false,
        };
    }

    private function libelle(Model $cible): string
    {
        return $cible instanceof Reproducteur ? $cible->matricule : $cible->code;
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}