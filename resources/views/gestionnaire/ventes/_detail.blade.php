@use('App\Support\Monnaie')

<div class="space-y-4">
    <div class="rounded-lg bg-white p-4 shadow">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-xl font-bold">{{ $vente->numero }}</div>
                <div class="text-sm text-gray-500">
                    {{ $vente->date_vente->format('d/m/Y') }} · {{ $vente->client?->nom ?? 'Client de passage' }}
                </div>
                @if ($vente->client?->telephone)
                    <div class="text-xs text-gray-500">{{ $vente->client->telephone }}</div>
                @endif
                <div class="text-xs text-gray-500">Vendeur : {{ $vente->utilisateur?->nom_complet ?? '-' }}</div>
            </div>
            <x-badge-paiement :vente="$vente" />
        </div>
        @if ($vente->notes)
            <p class="mt-2 text-sm">{{ $vente->notes }}</p>
        @endif
    </div>

    <div class="overflow-x-auto rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Articles</h2>
        <table class="min-w-full text-sm">
            <thead class="text-left text-gray-500">
                <tr>
                    <th class="py-1 pr-3">Article</th>
                    <th class="py-1 pr-3">Qté</th>
                    <th class="py-1 pr-3">Poids</th>
                    <th class="py-1 pr-3">Prix</th>
                    <th class="py-1 text-right">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vente->lignes as $ligne)
                    <tr class="border-t">
                        <td class="py-2 pr-3">{{ $ligne->description }}</td>
                        <td class="py-2 pr-3">{{ $ligne->quantite }}</td>
                        <td class="py-2 pr-3">{{ $ligne->poids_kg !== null ? number_format($ligne->poids_kg, 2, ',', ' ').' kg' : '-' }}</td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ Monnaie::format($ligne->prix_applique, false) }} {{ $ligne->mode_prix->suffixe() }}</td>
                        <td class="py-2 text-right whitespace-nowrap">{{ Monnaie::format($ligne->montant) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <dl class="mt-3 space-y-1 border-t pt-3 text-sm">
            <div class="flex justify-between"><dt>Total brut</dt><dd>{{ Monnaie::format($vente->total_brut) }}</dd></div>
            @if ((float) $vente->remise_montant > 0)
                <div class="flex justify-between text-amber-700">
                    <dt>Remise ({{ rtrim(rtrim(number_format($vente->remise_pct, 2, ',', ''), '0'), ',') }} %)</dt>
                    <dd>− {{ Monnaie::format($vente->remise_montant) }}</dd>
                </div>
            @endif
            <div class="flex justify-between text-base font-bold"><dt>Total à payer</dt><dd>{{ Monnaie::format($vente->total_net) }}</dd></div>
            <div class="flex justify-between"><dt>Déjà payé</dt><dd>{{ Monnaie::format($vente->montant_paye) }}</dd></div>
            @if ($vente->solde() > 0)
                <div class="flex justify-between font-semibold text-red-700"><dt>Reste à payer</dt><dd>{{ Monnaie::format($vente->solde()) }}</dd></div>
            @endif
        </dl>
    </div>

    <div class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Paiements</h2>
        @forelse ($vente->paiements as $paiement)
            <div class="border-b py-2 text-sm last:border-0">
                <div class="flex justify-between">
                    <span>{{ $paiement->date_paiement->format('d/m/Y') }} · {{ $paiement->mode->libelle() }}@if ($paiement->operateur) ({{ $paiement->operateur }})@endif</span>
                    <span class="font-medium">{{ Monnaie::format($paiement->montant) }}</span>
                </div>
                <div class="text-xs text-gray-500">
                    @if ($paiement->reference) Réf. {{ $paiement->reference }} · @endif
                    {{ $paiement->utilisateur?->nom_complet ?? '-' }}
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun paiement enregistré.</p>
        @endforelse
    </div>
</div>