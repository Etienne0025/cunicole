<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\CategorieCharge;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Services\BilanFinancier;
use App\Support\Monnaie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChargeController extends Controller
{
    public function index(Request $request, BilanFinancier $bilan)
    {
        $periode = BilanFinancier::resoudrePeriode($request->input('periode'));

        $charges = Charge::with('utilisateur')
            ->whereBetween('date_charge', [$periode['debut']->toDateString(), $periode['fin']->toDateString()])
            ->orderByDesc('date_charge')->orderByDesc('id')
            ->get();

        return view('proprietaire.charges.index', [
            'periode' => $periode,
            'charges' => $charges,
            'totaux' => $bilan->charges($periode['debut'], $periode['fin']),
            'fixes' => CategorieCharge::fixes(),
            'courantes' => CategorieCharge::courantes(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'categorie' => ['required', Rule::enum(CategorieCharge::class)],
            'libelle' => ['required', 'string', 'min:3', 'max:150'],
            'montant' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'date_charge' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [], [
            'categorie' => 'catégorie',
            'libelle' => 'désignation',
            'montant' => 'montant',
            'date_charge' => 'date',
        ]);

        Charge::create([
            ...$donnees,
            'montant' => Monnaie::arrondir($donnees['montant']),
            'utilisateur_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Charge enregistrée.');
    }

    public function destroy(Charge $charge)
    {
        $charge->delete();

        return back()->with('status', 'Charge supprimée.');
    }
}