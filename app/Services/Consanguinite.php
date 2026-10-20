<?php

namespace App\Services;

use App\Models\Reproducteur;

class Consanguinite
{
    /** Retourne le lien de parenté qui interdit la saillie, ou null si elle est autorisée. */
    public function lien(Reproducteur $femelle, Reproducteur $male): ?string
    {
        if ($femelle->pere_id && $femelle->pere_id == $male->id) {
            return 'père et fille';
        }
        if ($male->mere_id && $male->mere_id == $femelle->id) {
            return 'mère et fils';
        }

        $memeMere = $femelle->mere_id && $femelle->mere_id == $male->mere_id;
        $memePere = $femelle->pere_id && $femelle->pere_id == $male->pere_id;

        if ($memeMere && $memePere) {
            return 'frère et sœur';
        }
        if ($memeMere || $memePere) {
            return 'demi-frère et demi-sœur';
        }

        if (! config('cunicole.reproduction.consanguinite_etendue')) {
            return null;
        }

        $ancetresF = $this->ancetres($femelle);
        $ancetresM = $this->ancetres($male);

        if (($ancetresF[$male->id] ?? null) === 2 || ($ancetresM[$femelle->id] ?? null) === 2) {
            return 'grand-parent et petit-enfant';
        }

        $parentsF = $this->generation($ancetresF, 1);
        $parentsM = $this->generation($ancetresM, 1);
        $grandsF = $this->generation($ancetresF, 2);
        $grandsM = $this->generation($ancetresM, 2);

        if (array_intersect($parentsM, $grandsF)) {
            return 'oncle et nièce';
        }
        if (array_intersect($parentsF, $grandsM)) {
            return 'tante et neveu';
        }
        if (array_intersect($grandsF, $grandsM)) {
            return 'cousins';
        }

        return null;
    }

    /** @return array<int, int> [id de l'ancêtre => génération (1 = parent, 2 = grand-parent)] */
    private function ancetres(Reproducteur $animal): array
    {
        $resultat = [];
        $niveau = collect([$animal]);

        foreach ([1, 2] as $generation) {
            $ids = $niveau
                ->flatMap(fn (Reproducteur $a) => [$a->mere_id, $a->pere_id])
                ->filter()->unique()
                ->reject(fn ($id) => isset($resultat[$id]))
                ->values();

            if ($ids->isEmpty()) {
                break;
            }

            foreach ($ids as $id) {
                $resultat[$id] = $generation;
            }

            $niveau = Reproducteur::whereIn('id', $ids)->get();
        }

        return $resultat;
    }

    /** @return array<int, int> */
    private function generation(array $ancetres, int $generation): array
    {
        return array_keys(array_filter($ancetres, fn ($g) => $g === $generation));
    }
}