@use('App\Support\Monnaie')

<x-layouts.gestionnaire title="Ventes">
    <a href="{{ route('gestionnaire.ventes.create') }}" class="mb-4 block rounded bg-emerald-600 py-3 text-center text-white">+ Nouvelle vente</a>

    <section class="mb-4 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-3 font-semibold">Mes objectifs de vente</h2>
        @foreach ([['Cette semaine', $objectifs['semaine']], ['Ce mois', $objectifs['mois']]] as [$libelle, $o])
            <div class="mb-3 last:mb-0">
                <div class="flex justify-between text-sm">
                    <span>{{ $libelle }}</span>
                    <span class="font-medium">
                        {{ Monnaie::format($o['realise']) }}
                        @if ($o['objectif']) / {{ Monnaie::format($o['objectif']) }} @endif
                    </span>
                </div>
                @if ($o['pourcentage'] !== null)
                    <div class="mt-1 h-3 rounded bg-gray-200">
                        <div class="h-3 rounded {{ $o['pourcentage'] >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ min(100, $o['pourcentage']) }}%"></div>
                    </div>
                    <div class="text-right text-xs text-gray-500">{{ $o['pourcentage'] }} %</div>
                @else
                    <p class="text-xs text-gray-500">Aucun objectif fixé par le propriétaire.</p>
                @endif
            </div>
        @endforeach
    </section>

    <section class="mb-4 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 flex justify-between font-semibold">
            <span>Ventes d'aujourd'hui</span>
            <span>{{ Monnaie::format($totalJour) }}</span>
        </h2>
        @forelse ($ventesDuJour as $vente)
            <a href="{{ route('gestionnaire.ventes.show', $vente) }}" class="flex items-center justify-between border-b py-2 text-sm last:border-0">
                <div>
                    <div class="font-medium">{{ $vente->numero }} · {{ $vente->client?->nom ?? 'Client de passage' }}</div>
                    <div class="text-xs text-gray-500">{{ $vente->lignes->sum('quantite') }} animal(aux)</div>
                </div>
                <div class="text-right">
                    <div class="font-medium">{{ Monnaie::format($vente->total_net) }}</div>
                    <x-badge-paiement :vente="$vente" />
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucune vente aujourd'hui.</p>
        @endforelse
    </section>

    <section class="rounded-lg border border-red-200 bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold text-red-700">Soldes à encaisser</h2>
        @forelse ($aEncaisser as $vente)
            <a href="{{ route('gestionnaire.ventes.show', $vente) }}" class="flex items-center justify-between border-b py-2 text-sm last:border-0">
                <div>
                    <div class="font-medium">{{ $vente->client?->nom ?? 'Client de passage' }}</div>
                    <div class="text-xs text-gray-500">{{ $vente->numero }} · {{ $vente->date_vente->format('d/m/Y') }}</div>
                </div>
                <div class="font-semibold text-red-700">{{ Monnaie::format($vente->solde()) }}</div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucun solde en attente.</p>
        @endforelse
    </section>
</x-layouts.gestionnaire>