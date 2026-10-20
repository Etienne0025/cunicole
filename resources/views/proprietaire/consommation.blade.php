<x-layouts.proprietaire title="Consommation d'aliment">

    <div class="mb-4 flex gap-2 text-sm">
        @foreach ([7, 14, 30] as $periode)
            <a href="{{ route('proprietaire.consommation', ['jours' => $periode]) }}"
               class="rounded px-3 py-1 {{ $jours === $periode ? 'bg-slate-800 text-white' : 'border bg-white' }}">
                {{ $periode }} jours
            </a>
        @endforeach
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Théorique ({{ $jours }} j)</div>
            <div class="mt-1 text-3xl font-bold">{{ number_format($totalTheorique, 1, ',', ' ') }} <span class="text-base font-normal">kg</span></div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Servi ({{ $jours }} j)</div>
            <div class="mt-1 text-3xl font-bold">{{ number_format($totalServi, 1, ',', ' ') }} <span class="text-base font-normal">kg</span></div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Écart global</div>
            <div class="mt-1 text-3xl font-bold">
                @if ($ecartGlobal !== null) {{ $ecartGlobal > 0 ? '+' : '' }}{{ number_format($ecartGlobal, 1, ',', ' ') }} % @else - @endif
            </div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Jours en anomalie (écart &gt; {{ $seuil }} %)</div>
            <div class="mt-1 text-3xl font-bold {{ $joursAnormaux > 0 ? 'text-red-600' : '' }}">{{ $joursAnormaux }}</div>
        </div>
    </div>

    <div class="mb-6 rounded-lg bg-white p-5 shadow">
        <h2 class="mb-3 font-semibold">Consommation réelle et théorique par jour (kg)</h2>
        <canvas id="graphique-consommation" height="110"></canvas>
        <p class="mt-2 text-xs text-gray-500">
            Une consommation nettement inférieure à la théorique peut signaler un problème sanitaire.
            Les jours sans distribution enregistrée apparaissent comme des vides dans la courbe.
        </p>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Détail par catégorie</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Catégorie</th>
                    <th class="px-4 py-2">Théorique (kg)</th>
                    <th class="px-4 py-2">Servi (kg)</th>
                    <th class="px-4 py-2">Écart</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($parCategorie as $ligne)
                    @php
                        $theorique = (float) $ligne->theorique;
                        $ecart = $theorique > 0 ? round(((float) $ligne->servi - $theorique) / $theorique * 100, 1) : null;
                    @endphp
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $ligne->categorie->libelle() }}</td>
                        <td class="px-4 py-2">{{ number_format($theorique, 2, ',', ' ') }}</td>
                        <td class="px-4 py-2">{{ number_format((float) $ligne->servi, 2, ',', ' ') }}</td>
                        <td class="px-4 py-2">@if ($ecart !== null) {{ $ecart > 0 ? '+' : '' }}{{ number_format($ecart, 1, ',', ' ') }} % @else - @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune distribution sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Détail par jour</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Jour</th>
                    <th class="px-4 py-2">Théorique (kg)</th>
                    <th class="px-4 py-2">Servi (kg)</th>
                    <th class="px-4 py-2">Écart</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($serie->reverse() as $ligne)
                    <tr class="border-t {{ $ligne['anomalie'] ? 'bg-red-50' : '' }}">
                        <td class="px-4 py-2">{{ $ligne['date']->translatedFormat('D d/m') }}</td>
                        <td class="px-4 py-2">{{ $ligne['theorique'] !== null ? number_format($ligne['theorique'], 2, ',', ' ') : '-' }}</td>
                        <td class="px-4 py-2">{{ $ligne['servi'] !== null ? number_format($ligne['servi'], 2, ',', ' ') : '-' }}</td>
                        <td class="px-4 py-2">
                            @if ($ligne['ecart'] !== null) {{ $ligne['ecart'] > 0 ? '+' : '' }}{{ number_format($ligne['ecart'], 1, ',', ' ') }} % @else - @endif
                        </td>
                        <td class="px-4 py-2">
                            @if ($ligne['anomalie'])
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Anomalie</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new Chart(document.getElementById('graphique-consommation'), {
                    type: 'line',
                    data: {
                        labels: @json($serie->map(fn ($l) => $l['date']->format('d/m'))->values()),
                        datasets: [
                            { label: 'Théorique (kg)', data: @json($serie->pluck('theorique')->values()), tension: 0.3 },
                            { label: 'Servi (kg)', data: @json($serie->pluck('servi')->values()), tension: 0.3 },
                        ],
                    },
                    options: { scales: { y: { beginAtZero: true } } },
                });
            });
        </script>
    @endpush
</x-layouts.proprietaire>