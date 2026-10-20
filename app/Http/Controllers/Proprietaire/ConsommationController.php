<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use Illuminate\Http\Request;

class ConsommationController extends Controller
{
    public function __invoke(Request $request)
    {
        $jours = in_array((int) $request->input('jours'), [7, 14, 30], true) ? (int) $request->input('jours') : 14;
        $debut = today()->subDays($jours - 1);
        $seuil = (float) config('cunicole.alimentation.ecart_alerte_pct', 20);

        $parJour = Distribution::query()
            ->whereDate('date_distribution', '>=', $debut->toDateString())
            ->selectRaw('date_distribution, sum(quantite_theorique_kg) as theorique, sum(quantite_servie_kg) as servi')
            ->groupBy('date_distribution')
            ->get()
            ->keyBy(fn ($ligne) => $ligne->date_distribution->toDateString());

        $serie = collect(range(0, $jours - 1))->map(function (int $i) use ($debut, $parJour, $seuil) {
            $jour = $debut->copy()->addDays($i);
            $ligne = $parJour->get($jour->toDateString());

            $theorique = $ligne ? round((float) $ligne->theorique, 2) : null;
            $servi = $ligne ? round((float) $ligne->servi, 2) : null;
            $ecart = ($theorique !== null && $theorique > 0) ? round(($servi - $theorique) / $theorique * 100, 1) : null;

            return [
                'date' => $jour,
                'theorique' => $theorique,
                'servi' => $servi,
                'ecart' => $ecart,
                'anomalie' => $ecart !== null && abs($ecart) > $seuil,
            ];
        });

        $parCategorie = Distribution::query()
            ->whereDate('date_distribution', '>=', $debut->toDateString())
            ->selectRaw('categorie, sum(quantite_theorique_kg) as theorique, sum(quantite_servie_kg) as servi')
            ->groupBy('categorie')
            ->get()
            ->sortBy(fn ($ligne) => $ligne->categorie->ordre())
            ->values();

        $totalTheorique = round((float) $serie->sum('theorique'), 2);
        $totalServi = round((float) $serie->sum('servi'), 2);

        return view('proprietaire.consommation', [
            'jours' => $jours,
            'seuil' => $seuil,
            'serie' => $serie,
            'parCategorie' => $parCategorie,
            'totalTheorique' => $totalTheorique,
            'totalServi' => $totalServi,
            'ecartGlobal' => $totalTheorique > 0 ? round(($totalServi - $totalTheorique) / $totalTheorique * 100, 1) : null,
            'joursAnormaux' => $serie->where('anomalie', true)->count(),
        ]);
    }
}