<?php

namespace App\Services;

use App\Enums\CategorieAlimentation;
use App\Enums\SexeAnimal;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Models\LotEngraissement;
use App\Models\Reproducteur;
use App\Models\Saillie;

class EffectifsAlimentation
{
    /**
     * Nombre d'animaux à nourrir par catégorie, à l'instant présent.
     *
     * @return array<string, int> clé : valeur de CategorieAlimentation
     */
    public function parCategorie(): array
    {
        $actif = StatutReproducteur::Actif->value;

        $males = Reproducteur::where('statut', $actif)->where('sexe', SexeAnimal::Male->value)->count();
        $femelles = Reproducteur::where('statut', $actif)->where('sexe', SexeAnimal::Femelle->value)->count();

        // Une femelle n'a qu'une saillie en cours à la fois : les trois états s'excluent
        $femellesAvec = fn (StatutSaillie $statut) => Saillie::where('statut', $statut->value)
            ->whereHas('femelle', fn ($q) => $q->where('statut', $actif))
            ->distinct()
            ->count('femelle_id');

        $allaitantes = $femellesAvec(StatutSaillie::MiseBas);
        $gestantes = $femellesAvec(StatutSaillie::Gestante);

        return [
            CategorieAlimentation::Male->value => $males,
            CategorieAlimentation::FemelleVide->value => max(0, $femelles - $allaitantes - $gestantes),
            CategorieAlimentation::FemelleGestante->value => $gestantes,
            CategorieAlimentation::FemelleAllaitante->value => $allaitantes,
            CategorieAlimentation::Engraissement->value => (int) LotEngraissement::where('statut', StatutLot::EnCours->value)->sum('effectif_actuel'),
        ];
    }

    public function pour(CategorieAlimentation $categorie): int
    {
        return $this->parCategorie()[$categorie->value];
    }
}