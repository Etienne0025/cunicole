<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\CategorieAlimentation;
use App\Http\Controllers\Controller;
use App\Models\Aliment;
use App\Models\Ration;
use App\Services\EffectifsAlimentation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RationController extends Controller
{
    private const ATTRIBUTS = [
        'categorie' => 'catégorie',
        'aliment_id' => 'aliment',
        'grammes_par_jour' => 'grammes par jour',
    ];

    public function index(EffectifsAlimentation $effectifs)
    {
        return view('proprietaire.rations.index', [
            'categories' => CategorieAlimentation::cases(),
            'rations' => Ration::with('aliment')->orderBy('id')->get()->groupBy(fn (Ration $r) => $r->categorie->value),
            'aliments' => Aliment::where('actif', true)->orderBy('nom')->get(),
            'effectifs' => $effectifs->parCategorie(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'categorie' => ['required', Rule::enum(CategorieAlimentation::class)],
            'aliment_id' => [
                'required',
                Rule::exists('aliments', 'id')->where('actif', true),
                Rule::unique('rations', 'aliment_id')->where('categorie', $request->input('categorie')),
            ],
            'grammes_par_jour' => ['required', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], [
            'aliment_id.unique' => 'Cet aliment est déjà dans la formule de cette catégorie : modifiez la ligne existante.',
        ], self::ATTRIBUTS);

        Ration::create($donnees);

        return back()->with('status', 'Ration ajoutée. Les tâches de distribution apparaîtront dans la checklist du gestionnaire.');
    }

    public function update(Request $request, Ration $ration)
    {
        $donnees = $request->validate([
            'grammes_par_jour' => ['required', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:200'],
        ], [], self::ATTRIBUTS);

        $actif = $request->boolean('actif');

        if ($actif && ! $ration->aliment->actif) {
            throw ValidationException::withMessages([
                'actif' => "Impossible d'activer cette ration : l'aliment {$ration->aliment->nom} est désactivé.",
            ]);
        }

        $ration->update([...$donnees, 'actif' => $actif]);

        return back()->with('status', 'Ration modifiée.');
    }
}