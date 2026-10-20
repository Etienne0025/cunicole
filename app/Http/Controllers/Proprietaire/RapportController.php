<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\ClotureJournee;
use App\Services\GestionTaches;
use Illuminate\Support\Carbon;

class RapportController extends Controller
{
    public function index(GestionTaches $gestion)
    {
        return view('proprietaire.rapports.index', [
            'clotures' => ClotureJournee::with('utilisateur')->orderByDesc('date')->paginate(20),
            'joursNonClotures' => $gestion->joursNonClotures(),
        ]);
    }

    public function show(string $date, GestionTaches $gestion)
    {
        try {
            $jour = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless($jour->toDateString() === $date, 404);

        $cloture = ClotureJournee::with('utilisateur')->whereDate('date', $date)->first();
        $taches = $gestion->tachesDuJour($jour);

        abort_if($cloture === null && $taches->isEmpty(), 404);

        return view('proprietaire.rapports.show', [
            'jour' => $jour,
            'cloture' => $cloture,
            'taches' => $taches,
            'progression' => $gestion->progression($jour),
        ]);
    }
}