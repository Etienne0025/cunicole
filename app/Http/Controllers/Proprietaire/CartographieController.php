<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\StatutCage;
use App\Http\Controllers\Controller;
use App\Models\Batiment;
use App\Models\Cage;

class CartographieController extends Controller
{
    public function __invoke()
    {
        $parStatut = Cage::whereNotNull('activee_le')
            ->selectRaw('statut, count(*) as n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $stats = [
            'total' => Cage::count(),
            'activees' => Cage::whereNotNull('activee_le')->count(),
            'vides' => $parStatut[StatutCage::Vide->value] ?? 0,
            'occupees' => $parStatut[StatutCage::Occupee->value] ?? 0,
            'a_nettoyer' => $parStatut[StatutCage::ANettoyer->value] ?? 0,
            'capacite' => (int) Cage::whereNotNull('activee_le')->sum('capacite'),
        ];

        return view('proprietaire.cartographie', [
            'stats' => $stats,
            'batiments' => Batiment::with(['cages' => fn ($q) => $q->orderBy('code')])->orderBy('nom')->get(),
        ]);
    }
}