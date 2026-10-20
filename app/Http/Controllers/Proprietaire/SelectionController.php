<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Http\Controllers\Controller;
use App\Models\CritereSelection;
use App\Models\LotEngraissement;
use App\Models\Reproducteur;
use App\Services\PlacementCages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectionController extends Controller
{
    public function index()
    {
        $ageReforme = config('cunicole.selection.age_reforme_mois');

        $actifsValides = fn () => Reproducteur::where('statut', StatutReproducteur::Actif->value)->whereNotNull('valide_le');

        $releve = Reproducteur::whereNotNull('lot_origine_id')
            ->where('valide_le', '>=', now()->subYear())->count();

        $sorties = Reproducteur::whereNotNull('valide_le')
            ->whereIn('statut', [StatutReproducteur::Reforme->value, StatutReproducteur::Vendu->value, StatutReproducteur::Mort->value])
            ->where('date_sortie', '>=', today()->subYear()->toDateString())->count();

        $effectif = $actifsValides()->count();
        $ages = $actifsValides()->with('cage')
            ->where('date_naissance', '<=', today()->subMonths($ageReforme)->toDateString())
            ->orderBy('date_naissance')->get();

        return view('proprietaire.selection.index', [
            'criteres' => CritereSelection::actuel(),
            'enAttente' => Reproducteur::with(['cage', 'lotOrigine', 'mere', 'pere', 'pesees'])
                ->where('statut', StatutReproducteur::Actif->value)
                ->whereNull('valide_le')->orderBy('created_at')->get(),
            'lots' => LotEngraissement::with(['cage', 'portee', 'dernierePesee'])
                ->where('statut', StatutLot::EnCours->value)->orderBy('date_debut')->get(),
            'effectif' => $effectif,
            'ageReforme' => $ageReforme,
            'ages' => $ages,
            'releve' => $releve,
            'sorties' => $sorties,
            'tauxRemplacement' => $sorties > 0 ? round($releve / $sorties * 100) : null,
            'partAges' => $effectif > 0 ? round($ages->count() / $effectif * 100) : null,
        ]);
    }

    public function criteres(Request $request)
    {
        $donnees = $request->validate([
            'poids_min_kg' => ['required', 'numeric', 'min:0.5', 'max:10'],
            'age_min_jours' => ['required', 'integer', 'min:30', 'max:365'],
            'prolificite_mere_min' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ], [], [
            'poids_min_kg' => 'poids minimum',
            'age_min_jours' => 'âge minimum',
            'prolificite_mere_min' => 'prolificité minimale de la mère',
        ]);

        CritereSelection::actuel()->update($donnees);

        return back()->with('status', 'Critères de sélection enregistrés.');
    }

    public function valider(Request $request, Reproducteur $reproducteur)
    {
        abort_unless($reproducteur->estActif() && ! $reproducteur->estValide(), 422, 'Cet animal ne peut pas être validé.');

        $reproducteur->update(['valide_le' => now(), 'valide_par_id' => $request->user()->id]);

        return back()->with('status', "{$reproducteur->matricule} est officiellement intégré au cheptel reproducteur.");
    }

    public function refuser(Request $request, Reproducteur $reproducteur, PlacementCages $placement)
    {
        abort_unless($reproducteur->estActif() && ! $reproducteur->estValide(), 422, 'Cet animal ne peut pas être refusé.');

        DB::transaction(function () use ($reproducteur, $placement, $request) {
            $placement->retirer($reproducteur, $request->user(), 'Refusé à la validation');
            $reproducteur->update([
                'statut' => StatutReproducteur::Reforme,
                'date_sortie' => today(),
                'motif_sortie' => 'Refusé à la validation du propriétaire',
            ]);
        });

        return back()->with('status', "{$reproducteur->matricule} refusé. L'animal est réformé et sa cage est libérée.");
    }
}