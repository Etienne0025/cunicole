<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\OrigineReproducteur;
use App\Enums\SexeAnimal;
use App\Enums\StatutReproducteur;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReproducteurRequest;
use App\Models\Cage;
use App\Models\Reproducteur;
use App\Services\PlacementCages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\CycleReproduction;

class ReproducteurController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->input('statut', StatutReproducteur::Actif->value);

        $reproducteurs = Reproducteur::with('cage')
            ->when($statut !== 'tous', fn ($q) => $q->where('statut', $statut))
            ->when($request->sexe, fn ($q, $sexe) => $q->where('sexe', $sexe))
            ->when($request->q, fn ($q, $terme) => $q->where(
                fn ($q) => $q->where('matricule', 'like', "%{$terme}%")->orWhere('nom', 'like', "%{$terme}%")
            ))
            ->orderBy('matricule')
            ->paginate(30)
            ->withQueryString();

        return view('gestionnaire.reproducteurs.index', [
            'reproducteurs' => $reproducteurs,
            'sexes' => SexeAnimal::cases(),
            'statuts' => StatutReproducteur::cases(),
            'statutChoisi' => $statut,
        ]);
    }

    public function create()
    {
        return view('gestionnaire.reproducteurs.create', $this->donneesFormulaire(null));
    }

    public function store(ReproducteurRequest $request)
    {
       $reproducteur = Reproducteur::create([
    ...$request->validated(),
    'statut' => StatutReproducteur::Actif,
    'valide_le' => now(),
    'valide_par_id' => $request->user()->id,
]);
        return redirect()->route('gestionnaire.reproducteurs.show', $reproducteur)
            ->with('status', 'Reproducteur enregistré. Placez-le maintenant dans une cage.');
    }

    public function show(Reproducteur $reproducteur, PlacementCages $placement)
    {
        $reproducteur->load(['cage.batiment', 'mere', 'pere']);

        return view('gestionnaire.reproducteurs.show', [
            'reproducteur' => $reproducteur,
            'cagesDisponibles' => $reproducteur->estActif() ? $placement->cagesDisponibles($reproducteur) : collect(),
            'pesees' => $reproducteur->pesees()->latest('date_pesee')->limit(10)->get(),
            'mouvements' => $reproducteur->mouvements()->with('utilisateur')->latest('effectue_le')->limit(15)->get(),
        ]);
    }

    public function edit(Reproducteur $reproducteur)
    {
        return view('gestionnaire.reproducteurs.edit', $this->donneesFormulaire($reproducteur));
    }

    public function update(ReproducteurRequest $request, Reproducteur $reproducteur)
    {
        $reproducteur->update($request->validated());

        return redirect()->route('gestionnaire.reproducteurs.show', $reproducteur)
            ->with('status', 'Fiche modifiée.');
    }

    public function deplacer(Request $request, Reproducteur $reproducteur, PlacementCages $placement)
    {
        $donnees = $request->validate([
            'cage_id' => ['required', 'exists:cages,id'],
            'motif' => ['nullable', 'string', 'max:100'],
        ], [], ['cage_id' => 'cage', 'motif' => 'motif']);

        $placement->placer(
            $reproducteur,
            Cage::findOrFail($donnees['cage_id']),
            $request->user(),
            $donnees['motif'] ?? null,
        );

        return back()->with('status', 'Animal placé dans la cage.');
    }

   public function sortie(Request $request, Reproducteur $reproducteur, PlacementCages $placement, CycleReproduction $cycle)
{
    abort_unless($reproducteur->estActif(), 422, "Cet animal a déjà quitté le cheptel.");

    $donnees = $request->validate([
       'statut' => ['required', Rule::in([StatutReproducteur::Reforme->value])],
        'date_sortie' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$reproducteur->date_entree->toDateString()],
        'motif_sortie' => ['nullable', 'string', 'max:200'],
    ], [], ['statut' => 'type de sortie', 'date_sortie' => 'date de sortie', 'motif_sortie' => 'motif']);

    DB::transaction(function () use ($reproducteur, $donnees, $placement, $cycle, $request) {
        $placement->retirer($reproducteur, $request->user(), 'Sortie du cheptel');
        $cycle->interrompreSaillies($reproducteur);
        $reproducteur->update($donnees);
    });

    return back()->with('status', 'Sortie enregistrée. La cage a été libérée.');
}

    private function donneesFormulaire(?Reproducteur $reproducteur): array
    {
        $parents = fn (SexeAnimal $sexe) => Reproducteur::where('sexe', $sexe)
            ->when($reproducteur, fn ($q) => $q->whereKeyNot($reproducteur->id))
            ->orderBy('matricule')
            ->get();

        return [
            'reproducteur' => $reproducteur,
            'sexes' => SexeAnimal::cases(),
            'origines' => OrigineReproducteur::cases(),
            'meres' => $parents(SexeAnimal::Femelle),
            'peres' => $parents(SexeAnimal::Male),
        ];
    }
}