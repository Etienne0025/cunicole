<?php

namespace App\Http\Controllers\Proprietaire;

use App\Enums\CibleVaccination;
use App\Http\Controllers\Controller;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProtocoleVaccinalController extends Controller
{
    public function index()
    {
        return view('proprietaire.protocoles.index', [
            'protocoles' => ProtocoleVaccinal::with('produit')->orderByDesc('actif')->orderBy('nom')->get(),
            'vaccins' => ProduitVeterinaire::where('type', 'vaccin')->where('actif', true)->orderBy('nom')->get(),
            'cibles' => CibleVaccination::cases(),
        ]);
    }

    public function store(Request $request)
    {
        ProtocoleVaccinal::create($this->valider($request));

        return back()->with('status', 'Protocole ajouté au calendrier prophylactique. Les rappels apparaîtront dans la checklist du gestionnaire.');
    }

    public function update(Request $request, ProtocoleVaccinal $protocole)
    {
        $protocole->update([...$this->valider($request), 'actif' => $request->boolean('actif')]);

        return back()->with('status', 'Protocole modifié.');
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'produit_id' => ['required', Rule::exists('produits_veterinaires', 'id')->where('type', 'vaccin')],
            'cible' => ['required', Rule::enum(CibleVaccination::class)],
            'age_premiere_dose_jours' => ['required', 'integer', 'min:0', 'max:730'],
            'intervalle_rappel_jours' => ['nullable', 'integer', 'min:1', 'max:1095'],
            'doses_par_animal' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'nom' => 'nom du protocole',
            'produit_id' => 'vaccin',
            'cible' => 'cible',
            'age_premiere_dose_jours' => 'âge de la première dose',
            'intervalle_rappel_jours' => 'intervalle de rappel',
            'doses_par_animal' => 'doses par animal',
        ]);
    }
}