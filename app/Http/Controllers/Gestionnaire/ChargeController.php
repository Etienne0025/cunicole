<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\CategorieCharge;
use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Support\Monnaie;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChargeController extends Controller
{
    public function index()
    {
        $charges = Charge::with('utilisateur')
            ->whereIn('categorie', CategorieCharge::valeursCourantes())
            ->where('date_charge', '>=', today()->subDays(29)->toDateString())
            ->orderByDesc('date_charge')->orderByDesc('id')
            ->get();

        return view('gestionnaire.depenses.index', [
            'charges' => $charges,
            'categories' => CategorieCharge::courantes(),
            'totalMois' => (float) $charges->filter(fn (Charge $c) => $c->date_charge->isSameMonth(today()))->sum('montant'),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'categorie' => ['required', Rule::in(CategorieCharge::valeursCourantes())],
            'libelle' => ['required', 'string', 'min:3', 'max:150'],
            'montant' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'date_charge' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(7)->toDateString()],
            'notes' => ['nullable', 'string', 'max:300'],
        ], [
            'date_charge.after_or_equal' => 'Vous pouvez saisir une dépense des 7 derniers jours au plus.',
        ], [
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

        return back()->with('status', 'Dépense enregistrée.');
    }

    public function destroy(Request $request, Charge $charge)
    {
        $autorise = $charge->utilisateur_id === $request->user()->id
            && $charge->created_at->isToday()
            && ! $charge->categorie->estFixe();

        if (! $autorise) {
            return back()->withErrors(['action' => 'Vous ne pouvez annuler que vos propres dépenses saisies aujourd\'hui. Pour toute autre correction, voyez le propriétaire.']);
        }

        $charge->delete();

        return back()->with('status', 'Dépense annulée.');
    }
}