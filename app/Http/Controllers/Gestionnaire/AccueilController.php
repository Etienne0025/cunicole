<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\StatutCage;
use App\Http\Controllers\Controller;
use App\Models\Cage;
use App\Services\GestionTaches;

class AccueilController extends Controller
{
    public function __invoke(GestionTaches $taches)
    {
        $taches->synchroniser();

        return view('gestionnaire.accueil', [
            'cagesANettoyer' => Cage::where('statut', StatutCage::ANettoyer->value)->count(),
            'progression' => $taches->progression(today()),
            'joursNonClotures' => $taches->joursNonClotures(),
        ]);
    }
}