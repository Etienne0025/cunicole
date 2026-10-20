<?php

namespace App\Http\Controllers\Gestionnaire;

use App\Enums\TypeMouvementAliment;
use App\Http\Controllers\Controller;
use App\Models\Aliment;
use App\Models\Distribution;
use App\Models\MouvementAliment;
use App\Models\Ration;
use App\Services\EffectifsAlimentation;
use App\Services\GestionAliments;
use App\Services\GestionTaches;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AlimentationController extends Controller
{
    public function index(GestionTaches $taches, GestionAliments $aliments, EffectifsAlimentation $effectifs)
    {
        $taches->synchroniser();

        $nombres = $effectifs->parCategorie();
        $faites = Distribution::whereDate('date_distribution', today()->toDateString())->get()->keyBy('ration_id');

        $lignes = Ration::with('aliment')
            ->where('actif', true)
            ->whereHas('aliment', fn ($q) => $q->where('actif', true))
            ->get()
            ->map(fn (Ration $ration) => [
                'ration' => $ration,
                'nombre' => $nombres[$ration->categorie->value] ?? 0,
                'theorique' => $ration->quantiteTheorique($nombres[$ration->categorie->value] ?? 0),
                'distribution' => $faites->get($ration->id),
            ])
            ->filter(fn (array $ligne) => $ligne['nombre'] > 0 || $ligne['distribution'] !== null)
            ->sortBy(fn (array $ligne) => $ligne['ration']->categorie->ordre())
            ->values();

        $actifs = Aliment::where('actif', true)->orderBy('nom')->get();

        return view('gestionnaire.alimentation.index', [
            'lignes' => $lignes,
            'etats' => $aliments->etatStocks()->filter(fn (array $etat) => $etat['aliment']->actif)->values(),
            'aliments' => $actifs,
            'poidsSacs' => $actifs->pluck('poids_sac_kg', 'id'),
            'receptions' => MouvementAliment::with(['aliment', 'utilisateur'])
                ->where('type', TypeMouvementAliment::Reception->value)
                ->latest('date_mouvement')->latest('id')->limit(10)->get(),
        ]);
    }

    public function distribuer(Request $request, Ration $ration, GestionAliments $aliments)
    {
        $donnees = $request->validate([
            'quantite_servie_kg' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ], [], ['quantite_servie_kg' => 'quantité servie']);

      $distribution = $aliments->distribuer($ration, (float) $donnees['quantite_servie_kg'], $request->user(), HorsLigne::moment($request));

        return back()->with('status', 'Distribution enregistrée : '.number_format($distribution->quantite_servie_kg, 2, ',', ' ').' kg servis.');
    }

    public function recevoir(Request $request, GestionAliments $aliments)
    {
        $donnees = $request->validate([
            'aliment_id' => ['required', Rule::exists('aliments', 'id')->where('actif', true)],
            'nombre_sacs' => ['required', 'integer', 'min:1', 'max:1000'],
            'poids_sac_kg' => ['required', 'numeric', 'min:0.1', 'max:5000'],
            'date_reception' => ['required', 'date', 'before_or_equal:today'],
            'fournisseur' => ['nullable', 'string', 'max:100'],
            'cout_total' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
        ], [], [
            'aliment_id' => 'aliment',
            'nombre_sacs' => 'nombre de sacs',
            'poids_sac_kg' => "poids d'un sac",
            'date_reception' => 'date de réception',
            'cout_total' => 'montant payé',
        ]);

        $aliment = Aliment::findOrFail($donnees['aliment_id']);

        $mouvement = $aliments->recevoir(
            $aliment,
            (int) $donnees['nombre_sacs'],
            (float) $donnees['poids_sac_kg'],
            Carbon::parse($donnees['date_reception']),
            $donnees['fournisseur'] ?? null,
            isset($donnees['cout_total']) ? (float) $donnees['cout_total'] : null,
            $request->user(),
        );

        return back()->with('status', 'Réception enregistrée : +'.number_format($mouvement->quantite_kg, 2, ',', ' ')." kg de {$aliment->nom} en stock.");
    }
}