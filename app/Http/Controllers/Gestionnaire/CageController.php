<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\StatutCage;
use App\Enums\TypeCage;
use App\Http\Controllers\Controller;
use App\Models\Batiment;
use App\Models\Cage;
use App\Services\GenerateurQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CageController extends Controller
{
    private const ATTRIBUTS = [
        'batiment_id' => 'bâtiment',
        'prefixe' => 'préfixe',
        'nombre' => 'nombre de cages',
        'capacite' => 'capacité',
        'type' => 'type',
        'cages' => 'cages',
    ];

    public function index(Request $request)
    {
        $cages = Cage::with('batiment')
            ->when($request->batiment, fn ($q, $id) => $q->where('batiment_id', $id))
            ->when($request->statut, fn ($q, $statut) => $q->where('statut', $statut))
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('gestionnaire.cages.index', [
            'cages' => $cages,
            'batiments' => Batiment::orderBy('nom')->get(),
            'statuts' => StatutCage::cases(),
        ]);
    }

    public function create()
    {
        return view('gestionnaire.cages.create', [
            'batiments' => Batiment::orderBy('nom')->get(),
            'types' => TypeCage::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $donnees = $request->validate([
            'batiment_id' => ['required', 'exists:batiments,id'],
            'prefixe' => ['required', 'alpha_num', 'max:10'],
            'nombre' => ['required', 'integer', 'min:1', 'max:100'],
            'type' => ['required', Rule::enum(TypeCage::class)],
            'capacite' => ['required', 'integer', 'min:1', 'max:50'],
        ], [], self::ATTRIBUTS);

        $prefixe = Str::upper($donnees['prefixe']);

        // On poursuit la numérotation existante du préfixe (A-005 -> A-006...)
        $dernier = Cage::where('code', 'like', $prefixe.'-%')
            ->pluck('code')
            ->map(fn ($code) => (int) Str::afterLast($code, '-'))
            ->max() ?? 0;

        DB::transaction(function () use ($donnees, $prefixe, $dernier) {
            for ($i = 1; $i <= $donnees['nombre']; $i++) {
                Cage::create([
                    'batiment_id' => $donnees['batiment_id'],
                    'code' => sprintf('%s-%03d', $prefixe, $dernier + $i),
                    'type' => $donnees['type'],
                    'capacite' => $donnees['capacite'],
                    'statut' => StatutCage::Vide,
                ]);
            }
        });

        return redirect()->route('gestionnaire.cages.index')
            ->with('status', "{$donnees['nombre']} cage(s) créée(s). Sélectionnez-les pour imprimer leurs QR codes.");
    }

    public function show(Cage $cage, GenerateurQr $qr)
    {
        $cage->load(['batiment', 'reproducteurs', 'lots' => fn ($q) => $q->where('statut', 'en_cours')]);

        return view('gestionnaire.cages.show', [
            'cage' => $cage,
            'qrSvg' => $qr->svg(route('gestionnaire.cages.show', $cage), 160),
        ]);
    }

    public function edit(Cage $cage)
    {
        return view('gestionnaire.cages.edit', [
            'cage' => $cage,
            'batiments' => Batiment::orderBy('nom')->get(),
            'types' => TypeCage::cases(),
        ]);
    }

    public function update(Request $request, Cage $cage)
    {
        $donnees = $request->validate([
            'batiment_id' => ['required', 'exists:batiments,id'],
            'type' => ['required', Rule::enum(TypeCage::class)],
            'capacite' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], self::ATTRIBUTS);

        $cage->update($donnees);

        return redirect()->route('gestionnaire.cages.show', $cage)->with('status', 'Cage modifiée.');
    }

    public function destroy(Cage $cage)
    {
        if ($cage->statut === StatutCage::Occupee) {
            return back()->withErrors(['action' => 'Impossible de supprimer une cage occupée.']);
        }

        $cage->delete();

        return redirect()->route('gestionnaire.cages.index')->with('status', 'Cage supprimée.');
    }

    public function activer(Cage $cage)
    {
        if (! $cage->estActivee()) {
            $cage->update(['activee_le' => now()]);
        }

        return back()->with('status', 'Cage activée : elle est prête à recevoir des animaux.');
    }

    public function changerStatut(Request $request, Cage $cage)
    {
        $donnees = $request->validate([
            'statut' => ['required', Rule::in([StatutCage::Vide->value, StatutCage::ANettoyer->value])],
        ]);

        // Le statut « occupée » sera géré automatiquement à partir de la Phase 3
        if ($cage->statut === StatutCage::Occupee) {
            return back()->withErrors(['action' => "Une cage occupée doit d'abord être vidée de ses animaux."]);
        }

        $cage->update(['statut' => $donnees['statut']]);

        return back()->with('status', 'Statut mis à jour.');
    }

    public function planche(Request $request, GenerateurQr $qr)
    {
        $request->validate([
            'cages' => ['required', 'array', 'min:1', 'max:48'],
            'cages.*' => ['uuid', 'exists:cages,uuid'],
        ], [
            'cages.required' => 'Sélectionnez au moins une cage.',
            'cages.max' => 'Sélectionnez au maximum 48 cages à la fois.',
        ], self::ATTRIBUTS);

        $etiquettes = Cage::with('batiment')
            ->whereIn('uuid', $request->cages)
            ->orderBy('code')
            ->get()
            ->map(fn (Cage $cage) => [
                'cage' => $cage,
                'qr' => $qr->svgBase64(route('gestionnaire.cages.show', $cage)),
            ]);

        return Pdf::loadView('gestionnaire.cages.planche-qr', compact('etiquettes'))
            ->setPaper('a4')
            ->download('planche-qr-cages.pdf');
    }
}