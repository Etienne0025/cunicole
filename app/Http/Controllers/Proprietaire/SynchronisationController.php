<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\ActionSynchronisee;

class SynchronisationController extends Controller
{
    public function index()
    {
        $depuis = now()->subDays(7);

        $appliquees7j = ActionSynchronisee::where('statut', 'appliquee')
            ->where('traitee_le', '>=', $depuis)
            ->count();

        $refusees7j = ActionSynchronisee::where('statut', 'refusee')
            ->where('traitee_le', '>=', $depuis)
            ->count();

        $refusees = ActionSynchronisee::with('utilisateur')
            ->where('statut', 'refusee')
            ->latest('effectuee_le')
            ->paginate(20);

        return view('proprietaire.synchronisations', compact(
            'appliquees7j',
            'refusees7j',
            'refusees'
        ));
    }
}