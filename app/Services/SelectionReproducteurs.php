<?php

namespace App\Services;

use App\Enums\OrigineReproducteur;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Models\Cage;
use App\Models\CritereSelection;
use App\Models\LotEngraissement;
use App\Models\Portee;
use App\Models\Reproducteur;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelectionReproducteurs
{
    public function __construct(private PlacementCages $placement) {}

    /** Situation du lot par rapport aux critères en vigueur (pour l'affichage et la vérification). */
    public function situation(LotEngraissement $lot): array
    {
        $criteres = CritereSelection::actuel();
        $lot->loadMissing('portee.saillie.femelle', 'portee.saillie.male');

        $portee = $lot->portee;
        $mere = $portee?->saillie?->femelle;

        $age = $portee ? (int) $portee->date_mise_bas->diffInDays(today()) : null;
        $moyenneMere = $mere
            ? Portee::whereHas('saillie', fn ($q) => $q->where('femelle_id', $mere->id))->avg('nes_vivants')
            : null;

        return [
            'criteres' => $criteres,
            'portee' => $portee,
            'mere' => $mere,
            'pere' => $portee?->saillie?->male,
            'age' => $age,
            'age_ok' => $age !== null && $age >= $criteres->age_min_jours,
            'moyenne_mere' => $moyenneMere !== null ? round((float) $moyenneMere, 1) : null,
            'mere_ok' => $criteres->prolificite_mere_min === null
                || ($moyenneMere !== null && (float) $moyenneMere >= (float) $criteres->prolificite_mere_min),
        ];
    }

    /**
     * @param  array{matricule: string, nom?: ?string, sexe: string, poids_kg: float|string, cage_id: int}  $donnees
     */
    public function convertir(LotEngraissement $lot, array $donnees, Utilisateur $par): Reproducteur
    {
        return DB::transaction(function () use ($lot, $donnees, $par) {
            $lot = LotEngraissement::lockForUpdate()->findOrFail($lot->id);

            if ($lot->statut !== StatutLot::EnCours || $lot->effectif_actuel < 1) {
                $this->refuser('action', "Ce lot n'a plus de lapin à convertir.");
            }

            $s = $this->situation($lot);

            if ($s['portee'] === null) {
                $this->refuser('action', "Ce lot n'est rattaché à aucune portée : sa généalogie est inconnue.");
            }
            if (! $s['age_ok']) {
                $this->refuser('action', "Lot trop jeune : {$s['age']} jours (minimum {$s['criteres']->age_min_jours}).");
            }
            if ((float) $donnees['poids_kg'] < (float) $s['criteres']->poids_min_kg) {
                $this->refuser('poids_kg', "Poids insuffisant : {$donnees['poids_kg']} kg (minimum {$s['criteres']->poids_min_kg} kg).");
            }
            if (! $s['mere_ok']) {
                $moyenne = $s['moyenne_mere'] ?? 'inconnue';
                $this->refuser('action', "Prolificité de la mère insuffisante : {$moyenne} nés vivants en moyenne (minimum {$s['criteres']->prolificite_mere_min}).");
            }

            $reproducteur = Reproducteur::create([
                'matricule' => $donnees['matricule'],
                'nom' => $donnees['nom'] ?? null,
                'sexe' => $donnees['sexe'],
                'race' => $s['mere']?->race,
                'date_naissance' => $s['portee']->date_mise_bas,
                'origine' => OrigineReproducteur::Ferme,
                'mere_id' => $s['mere']?->id,
                'pere_id' => $s['pere']?->id,
                'statut' => StatutReproducteur::Actif,
                'date_entree' => today(),
                'lot_origine_id' => $lot->id,
                'valide_le' => null,
            ]);

            // Les règles de cage (type, activation, capacité) sont celles du service de placement
            $this->placement->placer(
                $reproducteur,
                Cage::findOrFail($donnees['cage_id']),
                $par,
                "Conversion du lot {$lot->code}",
            );

            $reproducteur->pesees()->create([
                'date_pesee' => today(),
                'poids_kg' => $donnees['poids_kg'],
                'utilisateur_id' => $par->id,
            ]);

            $lot->decrement('effectif_actuel');

            if ($lot->effectif_actuel === 0) {
                $lot->update(['statut' => StatutLot::Termine]);

                if ($lot->cage) {
                    $this->placement->actualiser($lot->cage);
                }
            }

            return $reproducteur;
        });
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}