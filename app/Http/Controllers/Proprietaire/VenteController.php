<?php

namespace App\Http\Controllers\Proprietaire;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Vente;
use App\Services\BilanFinancier;
use Illuminate\Http\Request;

class VenteController extends Controller
{
    public function index(Request $request)
    {
        $periode = BilanFinancier::resoudrePeriode($request->input('periode'));
        $filtre = $request->input('statut') === 'impayees' ? 'impayees' : 'toutes';

        $ventes = Vente::with(['client', 'utilisateur'])
            ->whereBetween('date_vente', [$periode['debut']->toDateString(), $periode['fin']->toDateString()])
            ->when($filtre === 'impayees', fn ($q) => $q->whereColumn('montant_paye', '<', 'total_net'))
            ->orderByDesc('date_vente')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $creances = Client::withSum('ventes as total_achats', 'total_net')
            ->withSum('ventes as total_paye', 'montant_paye')
            ->get()
            ->map(fn (Client $client) => [
                'client' => $client,
                'solde' => round((float) $client->total_achats - (float) $client->total_paye, 2),
            ])
            ->filter(fn (array $ligne) => $ligne['solde'] > 0)
            ->sortByDesc('solde')
            ->values();

        return view('proprietaire.ventes.index', [
            'periode' => $periode,
            'filtre' => $filtre,
            'ventes' => $ventes,
            'creances' => $creances,
        ]);
    }

    public function show(Vente $vente)
    {
        $vente->load(['client', 'lignes', 'paiements.utilisateur', 'utilisateur']);

        return view('proprietaire.ventes.show', ['vente' => $vente]);
    }
}