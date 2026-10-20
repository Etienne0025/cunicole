<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Services\GestionTaches;

class SupervisionController extends Controller
{
    public function index(GestionTaches $gestion)
    {
        // La génération est sans doublon : elle garantit une checklist même si le planificateur ne tourne pas
        $gestion->synchroniser();

        return view('proprietaire.supervision', [
            'etat' => $gestion->etat(today()),
            'joursNonClotures' => $gestion->joursNonClotures(),
        ]);
    }

    public function progression(GestionTaches $gestion)
    {
        $gestion->synchroniser();

        return response()->json($gestion->etat(today()));
    }
}