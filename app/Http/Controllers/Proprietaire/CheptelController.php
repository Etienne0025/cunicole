<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\SexeAnimal;
use App\Enums\StatutReproducteur;
use App\Http\Controllers\Controller;
use App\Models\Reproducteur;

class CheptelController extends Controller
{
    public function __invoke()
    {
        $actifs = fn () => Reproducteur::where('statut', StatutReproducteur::Actif->value);

        return view('proprietaire.cheptel', [
            'femelles' => $actifs()->where('sexe', SexeAnimal::Femelle->value)->count(),
            'males' => $actifs()->where('sexe', SexeAnimal::Male->value)->count(),
            'sansCage' => $actifs()->whereNull('cage_id')->count(),
            'reproducteurs' => Reproducteur::with('cage')
                ->where('statut', StatutReproducteur::Actif->value)
                ->orderBy('sexe')->orderBy('matricule')
                ->paginate(25),
        ]);
    }
}