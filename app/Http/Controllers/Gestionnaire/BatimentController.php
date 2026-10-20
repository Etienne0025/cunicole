<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Http\Controllers\Controller;
use App\Models\Batiment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BatimentController extends Controller
{
    public function index()
    {
        return view('gestionnaire.batiments.index', [
            'batiments' => Batiment::withCount('cages')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate(
            ['nom' => ['required', 'string', 'max:100', 'unique:batiments,nom']],
            [],
            ['nom' => 'nom du bâtiment']
        );

        Batiment::create($donnees);

        return back()->with('status', 'Bâtiment ajouté.');
    }

    public function update(Request $request, Batiment $batiment)
    {
        $donnees = $request->validate(
            ['nom' => ['required', 'string', 'max:100', Rule::unique('batiments', 'nom')->ignore($batiment->id)]],
            [],
            ['nom' => 'nom du bâtiment']
        );

        $batiment->update($donnees);

        return back()->with('status', 'Bâtiment renommé.');
    }

    public function destroy(Batiment $batiment)
    {
        if ($batiment->cages()->exists()) {
            return back()->withErrors(['action' => 'Impossible de supprimer un bâtiment qui contient des cages.']);
        }

        $batiment->delete();

        return back()->with('status', 'Bâtiment supprimé.');
    }
}