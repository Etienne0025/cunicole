<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\CategorieVente;
use App\Enums\ModeTarif;
use App\Http\Controllers\Controller;
use App\Models\ParametreVente;
use App\Models\Tarif;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TarifController extends Controller
{
    public function index()
    {
        return view('proprietaire.tarifs.index', [
            'categories' => CategorieVente::cases(),
            'tarifs' => Tarif::all()->keyBy(fn (Tarif $t) => $t->categorie->value),
            'modes' => ModeTarif::cases(),
            'parametres' => ParametreVente::actuel(),
        ]);
    }

    public function tarif(Request $request, string $categorie)
    {
        $cible = CategorieVente::tryFrom($categorie) ?? abort(404);

        $donnees = $request->validate([
            'mode' => ['required', Rule::enum(ModeTarif::class)],
            'prix' => ['required', 'numeric', 'min:1', 'max:100000000'],
        ], [], ['mode' => 'mode de tarification', 'prix' => 'prix']);

        Tarif::updateOrCreate(
            ['categorie' => $cible->value],
            [
                'mode' => $donnees['mode'],
                'prix' => $donnees['prix'],
                'actif' => $request->boolean('actif', true),
            ],
        );

        return back()->with('status', "Tarif « {$cible->libelle()} » enregistré.");
    }

    public function parametres(Request $request)
    {
        $donnees = $request->validate([
            'remise_max_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'objectif_hebdomadaire' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'objectif_mensuel' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
        ], [], [
            'remise_max_pct' => 'remise maximale',
            'objectif_hebdomadaire' => 'objectif hebdomadaire',
            'objectif_mensuel' => 'objectif mensuel',
        ]);

        ParametreVente::actuel()->update([
            ...$donnees,
            'credit_autorise' => $request->boolean('credit_autorise'),
        ]);

        return back()->with('status', 'Conditions de vente enregistrées.');
    }
}