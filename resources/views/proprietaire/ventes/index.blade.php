@use('App\Support\Monnaie')

<x-layouts.proprietaire title="Ventes et créances">

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        @foreach (['mois' => 'Mois en cours', 'mois_precedent' => 'Mois précédent', 'trimestre' => '3 derniers mois', 'annee' => 'Année en cours'] as $cle => $libelle)
            <a href="{{ route('proprietaire.ventes.index', ['periode' => $cle, 'statut' => $filtre === 'impayees' ? 'impayees' : null]) }}"
               class="rounded px-3 py-1 {{ $periode['cle'] === $cle ? 'bg-slate-800 text-white' : 'border bg-white' }}">{{ $libelle }}</a>
        @endforeach
        <span class="mx-2 text-gray-300">|</span>
        <a href="{{ route('proprietaire.ventes.index', ['periode' => $periode['cle']]) }}"
           class="rounded px-3 py-1 {{ $filtre === 'toutes' ? 'bg-slate-800 text-white' : 'border bg-white' }}">Toutes</a>
        <a href="{{ route('proprietaire.ventes.index', ['periode' => $periode['cle'], 'statut' => 'impayees']) }}"
           class="rounded px-3 py-1 {{ $filtre === 'impayees' ? 'bg-slate-800 text-white' : 'border bg-white' }}">Impayées</a>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">N°</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Client</th>
                    <th class="px-4 py-2">Vendeur</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2 text-right">Reste</th>
                    <th class="px-4 py-2">Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ventes as $vente)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium"><a class="text-blue-600 underline" href="{{ route('proprietaire.ventes.show', $vente) }}">{{ $vente->numero }}</a></td>
                        <td class="px-4 py-2">{{ $vente->date_vente->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $vente->client?->nom ?? 'Client de passage' }}</td>
                        <td class="px-4 py-2">{{ $vente->utilisateur?->nom_complet ?? '-' }}</td>
                        <td class="px-4 py-2 text-right">{{ Monnaie::format($vente->total_net) }}</td>
                        <td class="px-4 py-2 text-right {{ $vente->solde() > 0 ? 'text-red-600' : '' }}">{{ Monnaie::format($vente->solde()) }}</td>
                        <td class="px-4 py-2"><x-badge-paiement :vente="$vente" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Aucune vente sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-6">{{ $ventes->links() }}</div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Créances par client (toutes ventes)</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Client</th>
                    <th class="px-4 py-2">Téléphone</th>
                    <th class="px-4 py-2 text-right">Reste à payer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($creances as $ligne)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $ligne['client']->nom }}</td>
                        <td class="px-4 py-2">{{ $ligne['client']->telephone ?? '-' }}</td>
                        <td class="px-4 py-2 text-right text-red-600">{{ Monnaie::format($ligne['solde']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Aucune créance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>