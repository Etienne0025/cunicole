<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\CauseMortalite;
use App\Http\Controllers\Controller;
use App\Models\Mortalite;
use App\Models\Traitement;
use App\Services\CalendrierVaccinal;
use App\Services\SuiviMortalite;
use Illuminate\Http\Request;

class SanteController extends Controller
{
    public function index(Request $request, SuiviMortalite $suivi, CalendrierVaccinal $calendrier)
    {
        $etat = $suivi->taux();
        $depuis = today()->subDays($etat['jours'] - 1)->toDateString();
        $aujourdhui = today()->toDateString();

        $semaines = collect(range(7, 0))->map(function (int $i) {
            $debut = today()->startOfWeek()->subWeeks($i);

            return [
                'label' => $debut->format('d/m'),
                'n' => (int) Mortalite::whereBetween('date_deces', [$debut->toDateString(), $debut->copy()->endOfWeek()->toDateString()])->sum('nombre'),
            ];
        });

        $causes = Mortalite::where('date_deces', '>=', $depuis)
            ->selectRaw('cause, sum(nombre) as n')
            ->groupBy('cause')->orderByDesc('n')->get()
            ->map(fn ($ligne) => [
                'libelle' => CauseMortalite::from($ligne->cause)->libelle(),
                'n' => (int) $ligne->n,
            ]);

        return view('proprietaire.sante.index', [
            'etat' => $etat,
            'semaines' => $semaines,
            'causes' => $causes,
            'bloques' => Traitement::with('traitable')
                ->where('date_debut', '<=', $aujourdhui)->where('date_fin_attente', '>=', $aujourdhui)
                ->orderBy('date_fin_attente')->get(),
            'vaccinsDus' => $calendrier->echeances()->count(),
            'deces' => Mortalite::with('utilisateur')->latest('date_deces')->latest('id')->limit(15)->get(),
            'alertes' => $request->user()->unreadNotifications()->limit(10)->get(),
        ]);
    }

    public function alertesLues(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Alertes marquées comme lues.');
    }
}