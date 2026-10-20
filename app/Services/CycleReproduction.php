<?php

namespace App\Services;

use App\Enums\SexeAnimal;
use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutSaillie;
use App\Enums\TypeCage;
use App\Models\BlocageConsanguinite;
use App\Models\Cage;
use App\Models\LotEngraissement;
use App\Models\Portee;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Utilisateur;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CycleReproduction
{
    public function __construct(
        private Consanguinite $consanguinite,
        private PlacementCages $placement,
    ) {}

    public function enregistrerSaillie(
        Reproducteur $femelle,
        Reproducteur $male,
        CarbonInterface $date,
        ?Utilisateur $par = null,
        ?string $notes = null,
    ): Saillie {
        if ($femelle->sexe !== SexeAnimal::Femelle) {
            $this->refuser('femelle_id', "L'animal choisi comme femelle n'est pas une femelle.");
        }
        if ($male->sexe !== SexeAnimal::Male) {
            $this->refuser('male_id', "L'animal choisi comme mâle n'est pas un mâle.");
        }

        $ageMin = config('cunicole.reproduction.age_min_saillie_mois');

        foreach ([['femelle_id', $femelle, 'La femelle'], ['male_id', $male, 'Le mâle']] as [$champ, $animal, $libelle]) {
            if (! $animal->estActif()) {
                $this->refuser($champ, "{$libelle} {$animal->matricule} n'est plus dans le cheptel actif.");
            }

            $age = (int) $animal->date_naissance->diffInMonths($date);
            if ($age < $ageMin) {
                $this->refuser($champ, "{$libelle} {$animal->matricule} est trop jeune ({$age} mois ; minimum {$ageMin} mois).");
            }
        }

        if (Saillie::where('femelle_id', $femelle->id)->whereIn('statut', StatutSaillie::valeursEnCours())->exists()) {
            $this->refuser('femelle_id', "La femelle {$femelle->matricule} a déjà une saillie en cours.");
        }

        if ($lien = $this->consanguinite->lien($femelle, $male)) {
            // Enregistré hors transaction : la trace reste même si la saillie est refusée
            BlocageConsanguinite::create([
                'femelle_id' => $femelle->id,
                'male_id' => $male->id,
                'lien' => $lien,
                'utilisateur_id' => $par?->id,
            ]);

            $this->refuser('male_id', "Saillie bloquée : risque de consanguinité ({$lien}).");
        }

        return Saillie::create([
            'femelle_id' => $femelle->id,
            'male_id' => $male->id,
            'date_saillie' => $date,
            'statut' => StatutSaillie::EnAttente,
            'utilisateur_id' => $par?->id,
            'notes' => $notes,
        ]);
    }

    public function enregistrerPalpation(Saillie $saillie, bool $gestante, CarbonInterface $date): void
    {
        if ($saillie->statut !== StatutSaillie::EnAttente) {
            $this->refuser('action', 'La palpation de cette saillie est déjà enregistrée.');
        }
        if ($date->lt($saillie->date_saillie)) {
            $this->refuser('date_palpation', 'La palpation ne peut pas précéder la saillie.');
        }

        $saillie->update([
            'statut' => $gestante ? StatutSaillie::Gestante : StatutSaillie::NonGestante,
            'date_palpation' => $date,
        ]);
    }

    public function installerBoiteANids(Saillie $saillie, CarbonInterface $date): void
    {
        if ($saillie->statut !== StatutSaillie::Gestante) {
            $this->refuser('action', 'La boîte à nids ne concerne que les femelles gestantes.');
        }
        if ($saillie->boite_nids_le !== null) {
            $this->refuser('action', 'La boîte à nids est déjà installée.');
        }
        if ($date->lt($saillie->date_saillie)) {
            $this->refuser('boite_nids_le', "La date ne peut pas précéder la saillie.");
        }

        $saillie->update(['boite_nids_le' => $date]);
    }

    public function enregistrerMiseBas(
        Saillie $saillie,
        CarbonInterface $date,
        int $nesVivants,
        int $mortsNes,
        ?Utilisateur $par = null,
    ): Portee {
        if ($saillie->statut !== StatutSaillie::Gestante) {
            $this->refuser('action', 'Une mise bas ne peut être enregistrée que pour une femelle gestante.');
        }
        if ($date->lt($saillie->date_saillie)) {
            $this->refuser('date_mise_bas', 'La mise bas ne peut pas précéder la saillie.');
        }
        if ($nesVivants + $mortsNes < 1) {
            $this->refuser('nes_vivants', 'Indiquez au moins un lapereau (vivant ou mort-né).');
        }

        return DB::transaction(function () use ($saillie, $date, $nesVivants, $mortsNes, $par) {
            $portee = Portee::create([
                'saillie_id' => $saillie->id,
                'date_mise_bas' => $date,
                'nes_vivants' => $nesVivants,
                'morts_nes' => $mortsNes,
                'utilisateur_id' => $par?->id,
            ]);

            $saillie->update(['statut' => StatutSaillie::MiseBas]);

            return $portee;
        });
    }

    /** Sépare la portée de sa mère et crée le lot d'engraissement (null si aucun lapereau n'a survécu). */
    public function sevrer(
        Saillie $saillie,
        CarbonInterface $date,
        int $nombreSevres,
        ?Cage $cage,
    ): ?LotEngraissement {
        if ($saillie->statut !== StatutSaillie::MiseBas) {
            $this->refuser('action', 'Le sevrage ne concerne que les portées nées et non encore sevrées.');
        }

        $portee = $saillie->portee;

        if ($date->lt($portee->date_mise_bas)) {
            $this->refuser('date_sevrage', 'Le sevrage ne peut pas précéder la mise bas.');
        }
       $disponibles = $portee->vivantsActuels();

if ($nombreSevres > $disponibles) {
    $this->refuser('nombre_sevres', "Le nombre de sevrés ne peut pas dépasser les {$disponibles} lapereaux vivants de la portée.");
}
        if ($nombreSevres > 0 && $cage === null) {
            $this->refuser('cage_id', "Choisissez la cage d'engraissement du lot.");
        }

        return DB::transaction(function () use ($saillie, $portee, $date, $nombreSevres, $cage) {
            $lot = null;

            if ($nombreSevres > 0) {
                $cage = Cage::lockForUpdate()->findOrFail($cage->id);
                $this->verifierCageDeLot($cage, $nombreSevres);

                $prefixe = 'LOT-'.$date->year.'-';
                $dernier = LotEngraissement::where('code', 'like', $prefixe.'%')
                    ->pluck('code')
                    ->map(fn ($code) => (int) Str::afterLast($code, '-'))
                    ->max() ?? 0;

                $lot = LotEngraissement::create([
                    'code' => sprintf('%s%03d', $prefixe, $dernier + 1),
                    'portee_id' => $portee->id,
                    'cage_id' => $cage->id,
                    'effectif_initial' => $nombreSevres,
                    'effectif_actuel' => $nombreSevres,
                    'date_debut' => $date,
                    'statut' => StatutLot::EnCours,
                ]);
            }

            $portee->update(['date_sevrage' => $date, 'sevres' => $nombreSevres]);
            $saillie->update(['statut' => StatutSaillie::Sevree]);

            if ($lot) {
                $this->placement->actualiser($cage);
            }

            return $lot;
        });
    }

    private function verifierCageDeLot(Cage $cage, int $effectif): void
    {
        if (! $cage->estActivee()) {
            $this->refuser('cage_id', "La cage {$cage->code} n'est pas activée.");
        }
        if ($cage->type !== TypeCage::Engraissement) {
            $this->refuser('cage_id', "La cage {$cage->code} n'est pas une cage d'engraissement.");
        }
        if ($cage->statut !== StatutCage::Vide) {
            $this->refuser('cage_id', "La cage {$cage->code} n'est pas libre (statut : {$cage->statut->libelle()}).");
        }
        if ($cage->capacite < $effectif) {
            $this->refuser('cage_id', "La cage {$cage->code} est trop petite ({$cage->capacite} places pour {$effectif} lapins).");
        }
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }

    /** Une femelle qui meurt ou quitte le cheptel interrompt ses saillies en cours. */
public function interrompreSaillies(Reproducteur $femelle): int
{
    if ($femelle->sexe !== SexeAnimal::Femelle) {
        return 0;
    }

    $saillies = Saillie::where('femelle_id', $femelle->id)
        ->whereIn('statut', [StatutSaillie::EnAttente->value, StatutSaillie::Gestante->value])
        ->get();

    foreach ($saillies as $saillie) {
        $saillie->update(['statut' => StatutSaillie::Interrompue]);
    }

    return $saillies->count();
}
}