<?php

namespace App\Services;

use App\Enums\CibleVaccination;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Models\LotEngraissement;
use App\Models\ProtocoleVaccinal;
use App\Models\Reproducteur;
use App\Models\Vaccination;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CalendrierVaccinal
{
    /**
     * Vaccinations à faire à la date donnée, selon les protocoles actifs saisis par le propriétaire.
     *
     * @return Collection<int, array{protocole: ProtocoleVaccinal, cible: Model, motif: string}>
     */
    public function echeances(?CarbonInterface $jour = null): Collection
    {
        $jour = ($jour ?? today())->copy()->startOfDay();
        $resultat = collect();

        $protocoles = ProtocoleVaccinal::with('produit')->where('actif', true)->get();

        foreach ($protocoles->groupBy(fn (ProtocoleVaccinal $p) => $p->cible->value) as $cible => $liste) {
            $sujets = $cible === CibleVaccination::Lot->value ? $this->lots() : $this->reproducteurs();

            if ($sujets->isEmpty()) {
                continue;
            }

            $dernieres = Vaccination::query()
                ->where('vaccinable_type', $cible)
                ->whereIn('protocole_id', $liste->pluck('id'))
                ->selectRaw('vaccinable_id, protocole_id, max(date_vaccination) as derniere')
                ->groupBy('vaccinable_id', 'protocole_id')
                ->get()
                ->mapWithKeys(fn ($ligne) => [
                    "{$ligne->vaccinable_id}-{$ligne->protocole_id}" => Carbon::parse($ligne->derniere),
                ]);

            foreach ($liste as $protocole) {
                foreach ($sujets as $sujet) {
                    $age = $this->ageEnJours($sujet, $jour);

                    if ($age === null) {
                        continue;
                    }

                    $motif = $this->motifSiDu($protocole, $age, $dernieres["{$sujet->id}-{$protocole->id}"] ?? null, $jour);

                    if ($motif !== null) {
                        $resultat->push(['protocole' => $protocole, 'cible' => $sujet, 'motif' => $motif]);
                    }
                }
            }
        }

        return $resultat;
    }

    private function motifSiDu(ProtocoleVaccinal $protocole, int $age, ?Carbon $derniere, CarbonInterface $jour): ?string
    {
        if ($derniere === null) {
            return $age >= $protocole->age_premiere_dose_jours
                ? "Première dose (âge : {$age} jours)"
                : null;
        }

        if ($protocole->intervalle_rappel_jours === null) {
            return null;
        }

        $prevue = $derniere->copy()->addDays($protocole->intervalle_rappel_jours);

        return $prevue->lte($jour)
            ? 'Rappel prévu le '.$prevue->format('d/m/Y').' (dernière dose le '.$derniere->format('d/m/Y').')'
            : null;
    }

    private function ageEnJours(Model $sujet, CarbonInterface $jour): ?int
    {
        $naissance = $sujet instanceof LotEngraissement ? $sujet->portee?->date_mise_bas : $sujet->date_naissance;

        return $naissance ? max(0, (int) $naissance->diffInDays($jour, false)) : null;
    }

    private function reproducteurs(): Collection
    {
        return Reproducteur::where('statut', StatutReproducteur::Actif->value)->whereNotNull('valide_le')->get();
    }

    private function lots(): Collection
    {
        return LotEngraissement::with('portee')->where('statut', StatutLot::EnCours->value)->get();
    }
}