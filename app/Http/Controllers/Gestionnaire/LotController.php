<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\SexeAnimal;
use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\TypeCage;
use App\Http\Controllers\Controller;
use App\Models\Cage;
use App\Models\LotEngraissement;
use App\Services\SelectionReproducteurs;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LotController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->input('statut', StatutLot::EnCours->value);

        $lots = LotEngraissement::with(['cage', 'portee', 'dernierePesee'])
            ->when($statut !== 'tous', fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('date_debut')
            ->paginate(20)
            ->withQueryString();

        return view('gestionnaire.lots.index', ['lots' => $lots, 'statutChoisi' => $statut]);
    }

    public function show(LotEngraissement $lot, SelectionReproducteurs $selection)
    {
        $lot->load(['cage.batiment', 'portee']);

        $libres = fn (array $types) => Cage::with('batiment')
            ->whereNotNull('activee_le')
            ->where('statut', StatutCage::Vide->value)
            ->whereIn('type', array_map(fn (TypeCage $t) => $t->value, $types))
            ->orderBy('code')
            ->get();

        return view('gestionnaire.lots.show', [
            'lot' => $lot,
            'situation' => $selection->situation($lot),
            'pesees' => $lot->pesees()->latest('date_pesee')->latest('id')->limit(10)->get(),
            'issus' => $lot->reproducteurs()->orderBy('matricule')->get(),
            'cagesFemelles' => $libres(TypeCage::accepteursDe(SexeAnimal::Femelle)),
            'cagesMales' => $libres(TypeCage::accepteursDe(SexeAnimal::Male)),
        ]);
    }

    public function peser(Request $request, LotEngraissement $lot)
    {
        abort_unless($lot->statut === StatutLot::EnCours, 422, 'Ce lot est terminé.');

        $donnees = $request->validate([
            'date_pesee' => ['required', 'date', 'before_or_equal:today'],
            'poids_moyen_kg' => ['required', 'numeric', 'min:0.05', 'max:8'],
            'nombre_pese' => ['required', 'integer', 'min:1', 'max:'.$lot->effectif_actuel],
        ], [], [
            'date_pesee' => 'date de pesée',
            'poids_moyen_kg' => 'poids moyen',
            'nombre_pese' => 'nombre de lapins pesés',
        ]);

        $lot->pesees()->create([...$donnees, 'utilisateur_id' => $request->user()->id]);

        return back()->with('status', 'Pesée du lot enregistrée.');
    }

    public function convertir(Request $request, LotEngraissement $lot, SelectionReproducteurs $selection)
    {
        $request->merge(['matricule' => Str::upper(trim((string) $request->input('matricule')))]);

        $donnees = $request->validate([
            'matricule' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-\/]+$/', 'unique:reproducteurs,matricule'],
            'nom' => ['nullable', 'string', 'max:50'],
            'sexe' => ['required', Rule::enum(SexeAnimal::class)],
            'poids_kg' => ['required', 'numeric', 'min:0.5', 'max:15'],
            'cage_id' => ['required', 'exists:cages,id'],
        ], [
            'matricule.regex' => 'Le matricule ne doit contenir que des lettres, chiffres, tirets ou barres obliques.',
            'matricule.unique' => 'Ce matricule est déjà attribué à un autre animal.',
        ], [
            'matricule' => 'matricule (tatouage)',
            'poids_kg' => 'poids',
            'cage_id' => 'cage',
        ]);

        $reproducteur = $selection->convertir($lot, $donnees, $request->user());

        return redirect()->route('gestionnaire.reproducteurs.show', $reproducteur)
            ->with('status', "Lapin converti en reproducteur {$reproducteur->matricule}. Il est en attente de validation par le propriétaire.");
    }
}