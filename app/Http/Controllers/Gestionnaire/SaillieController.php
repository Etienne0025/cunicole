<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\SexeAnimal;
use App\Enums\StatutCage;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\TypeCage;
use App\Http\Controllers\Controller;
use App\Models\Cage;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Services\CycleReproduction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SaillieController extends Controller
{
    public function index(Request $request)
    {
        $saillies = Saillie::with(['femelle', 'male'])
            ->when($request->statut, fn ($q, $statut) => $q->where('statut', $statut))
            ->latest('date_saillie')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('gestionnaire.saillies.index', [
            'saillies' => $saillies,
            'statuts' => StatutSaillie::cases(),
        ]);
    }

    public function create(Request $request)
    {
        $actifs = fn (SexeAnimal $sexe) => Reproducteur::with('cage')
            ->where('sexe', $sexe->value)
            ->where('statut', StatutReproducteur::Actif->value)
            ->orderBy('matricule')
            ->get();

        return view('gestionnaire.saillies.create', [
            'femelles' => $actifs(SexeAnimal::Femelle),
            'males' => $actifs(SexeAnimal::Male),
            'femelleChoisie' => $request->integer('femelle') ?: null,
        ]);
    }

    public function store(Request $request, CycleReproduction $cycle)
    {
        $donnees = $request->validate([
            'femelle_id' => ['required', 'exists:reproducteurs,id'],
            'male_id' => ['required', 'exists:reproducteurs,id'],
            'date_saillie' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'femelle_id' => 'femelle',
            'male_id' => 'mâle',
            'date_saillie' => 'date de saillie',
        ]);

        $saillie = $cycle->enregistrerSaillie(
            Reproducteur::findOrFail($donnees['femelle_id']),
            Reproducteur::findOrFail($donnees['male_id']),
            Carbon::parse($donnees['date_saillie']),
            $request->user(),
            $donnees['notes'] ?? null,
        );

        return redirect()->route('gestionnaire.saillies.show', $saillie)
            ->with('status', 'Saillie enregistrée. Palpation prévue le '.$saillie->datePrevue('palpation')->format('d/m/Y').'.');
    }

    public function show(Saillie $saillie)
    {
        $saillie->load(['femelle.cage', 'male', 'portee.lot.cage']);

        $cagesEngraissement = $saillie->statut === StatutSaillie::MiseBas
            ? Cage::with('batiment')
                ->where('type', TypeCage::Engraissement->value)
                ->where('statut', StatutCage::Vide->value)
                ->whereNotNull('activee_le')
                ->orderBy('code')
                ->get()
            : collect();

        return view('gestionnaire.saillies.show', compact('saillie', 'cagesEngraissement'));
    }

    public function palpation(Request $request, Saillie $saillie, CycleReproduction $cycle)
    {
        $donnees = $request->validate([
            'resultat' => ['required', 'in:gestante,non_gestante'],
            'date_palpation' => ['required', 'date', 'before_or_equal:today'],
        ], [], ['resultat' => 'résultat', 'date_palpation' => 'date de palpation']);

        $cycle->enregistrerPalpation($saillie, $donnees['resultat'] === 'gestante', Carbon::parse($donnees['date_palpation']));

        return back()->with('status', $donnees['resultat'] === 'gestante'
            ? 'Femelle gestante. Boîte à nids à installer le '.$saillie->datePrevue('boite_nids')->format('d/m/Y').'.'
            : 'Femelle non gestante : la saillie est comptée comme un échec. Vous pouvez programmer une nouvelle saillie.');
    }

    public function boiteANids(Request $request, Saillie $saillie, CycleReproduction $cycle)
    {
        $donnees = $request->validate([
            'boite_nids_le' => ['required', 'date', 'before_or_equal:today'],
        ], [], ['boite_nids_le' => "date d'installation"]);

        $cycle->installerBoiteANids($saillie, Carbon::parse($donnees['boite_nids_le']));

        return back()->with('status', 'Boîte à nids enregistrée.');
    }
}