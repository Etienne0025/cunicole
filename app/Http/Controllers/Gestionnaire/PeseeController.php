<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\Reproducteur;
use Illuminate\Http\Request;

class PeseeController extends Controller
{
    public function store(Request $request, Reproducteur $reproducteur)
    {
        $donnees = $request->validate([
            'date_pesee' => ['required', 'date', 'before_or_equal:today'],
            'poids_kg' => ['required', 'numeric', 'min:0.1', 'max:15'],
        ], [], ['date_pesee' => 'date de pesée', 'poids_kg' => 'poids']);

        $reproducteur->pesees()->create([...$donnees, 'utilisateur_id' => $request->user()->id]);

        return back()->with('status', 'Pesée enregistrée.');
    }
}