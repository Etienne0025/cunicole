<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\ClotureJournee;
use App\Models\Tache;
use App\Services\GestionTaches;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Enums\TypeTache;
use App\Services\SoinsVeterinaires;

class TacheController extends Controller
{
    public function index(GestionTaches $gestion)
    {
        $gestion->synchroniser();

        return view('gestionnaire.taches.index', [
            'taches' => $gestion->tachesDuJour(today()),
            'progression' => $gestion->progression(today()),
            'cloturee' => $gestion->estCloturee(today()),
            'joursNonClotures' => $gestion->joursNonClotures(),
        ]);
    }

   public function cocher(Request $request, Tache $tache, GestionTaches $gestion, SoinsVeterinaires $soins)
{
    // Heure réelle de la saisie : celle du téléphone si elle a été faite hors ligne
    $moment = HorsLigne::moment($request);

    // La vaccination décompte le stock : elle ne se coche pas comme une tâche ordinaire
    if ($tache->type === TypeTache::Vaccination) {
        $soins->vaccinerDepuisTache($tache, $request->user(), $moment);

        return back()->with('status', 'Vaccination enregistrée. Le stock de la pharmacie a été mis à jour.');
    }

    $gestion->cocher($tache, $request->user(), $moment);

    return back()->with('status', 'Tâche marquée comme faite.');
}


    public function decocher(Tache $tache, GestionTaches $gestion)
    {
        $gestion->decocher($tache);

        return back()->with('status', 'Tâche remise à faire.');
    }

    public function cloture(Request $request, GestionTaches $gestion)
    {
        $donnees = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [], ['date' => 'date']);

        $jour = Carbon::parse($donnees['date'] ?? today()->toDateString())->startOfDay();

        if ($jour->isToday()) {
            $gestion->synchroniser($jour);
        }

        return view('gestionnaire.taches.cloture', [
            'jour' => $jour,
            'taches' => $gestion->tachesDuJour($jour),
            'progression' => $gestion->progression($jour),
            'cloture' => ClotureJournee::with('utilisateur')->whereDate('date', $jour->toDateString())->first(),
        ]);
    }

    public function cloturer(Request $request, GestionTaches $gestion)
    {
        $donnees = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'justifications' => ['nullable', 'array'],
            'justifications.*' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [], ['date' => 'date', 'commentaire' => 'commentaire']);

        $gestion->cloturer(
            Carbon::parse($donnees['date']),
            $donnees['justifications'] ?? [],
            $donnees['commentaire'] ?? null,
            $request->user(),
        );

        return redirect()->route('gestionnaire.taches.index')
            ->with('status', 'Journée clôturée. Le propriétaire peut consulter le rapport.');
    }
}