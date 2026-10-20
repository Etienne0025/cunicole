<x-layouts.proprietaire title="Santé et mortalités">

    @if ($alertes->isNotEmpty())
        <div class="mb-6 rounded-lg border border-red-300 bg-red-50 p-4">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold text-red-800">Alertes non lues</h2>
                <form method="POST" action="{{ route('proprietaire.sante.alertes_lues') }}">
                    @csrf
                    <button class="text-sm text-red-700 underline">Tout marquer comme lu</button>
                </form>
            </div>
            @foreach ($alertes as $alerte)
                <p class="border-t border-red-200 py-1 text-sm text-red-800">
                    {{ $alerte->created_at->format('d/m/Y H:i') }} · {{ $alerte->data['message'] }}
                </p>
            @endforeach
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Taux de mortalité ({{ $etat['jours'] }} jours)</div>
            <div class="mt-1 text-3xl font-bold {{ $etat['depasse'] ? 'text-red-600' : '' }}">
                {{ $etat['taux'] !== null ? number_format($etat['taux'], 1, ',', ' ').' %' : '-' }}
            </div>
            <div class="text-xs text-gray-500">Seuil critique : {{ $etat['seuil'] }} %</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Décès ({{ $etat['jours'] }} jours)</div>
            <div class="mt-1 text-3xl font-bold">{{ $etat['deces'] }}</div>
            <div class="text-xs text-gray-500">sur {{ $etat['effectif'] }} animaux vivants</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Vaccinations à faire</div>
            <div class="mt-1 text-3xl font-bold">{{ $vaccinsDus }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Bloqués pour la vente</div>
            <div class="mt-1 text-3xl font-bold">{{ $bloques->count() }}</div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Décès par semaine</h2>
            <canvas id="graphique-deces" height="140"></canvas>
        </div>

        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Causes suspectées ({{ $etat['jours'] }} jours)</h2>
            @forelse ($causes as $cause)
                <div class="flex justify-between border-b py-1 text-sm last:border-0">
                    <span>{{ $cause['libelle'] }}</span>
                    <span class="font-medium">{{ $cause['n'] }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aucun décès sur la période.</p>
            @endforelse
        </div>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Animaux et lots bloqués pour la vente (délai d'attente sanitaire)</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Animal ou lot</th>
                    <th class="px-4 py-2">Traitement</th>
                    <th class="px-4 py-2">Symptômes</th>
                    <th class="px-4 py-2">Libre à la vente le</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bloques as $traitement)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $traitement->libelleCible() }}</td>
                        <td class="px-4 py-2">{{ $traitement->nom_traitement }}</td>
                        <td class="px-4 py-2">{{ $traitement->symptomes }}</td>
                        <td class="px-4 py-2 text-red-700">{{ $traitement->date_fin_attente->copy()->addDay()->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucun animal bloqué.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Derniers décès</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Concerné</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Nombre</th>
                    <th class="px-4 py-2">Cause</th>
                    <th class="px-4 py-2">Déclaré par</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deces as $mortalite)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $mortalite->date_deces->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 font-medium">{{ $mortalite->code_cible }}</td>
                        <td class="px-4 py-2">{{ $mortalite->cible->libelle() }}</td>
                        <td class="px-4 py-2">{{ $mortalite->nombre }}</td>
                        <td class="px-4 py-2">
                            {{ $mortalite->cause->libelle() }}
                            @if ($mortalite->precisions)<div class="text-xs text-gray-500">{{ $mortalite->precisions }}</div>@endif
                        </td>
                        <td class="px-4 py-2">{{ $mortalite->utilisateur?->nom_complet ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun décès enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new Chart(document.getElementById('graphique-deces'), {
                    type: 'bar',
                    data: {
                        labels: @json($semaines->pluck('label')->values()),
                        datasets: [{ label: 'Décès', data: @json($semaines->pluck('n')->values()) }],
                    },
                    options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });
            });
        </script>
    @endpush
</x-layouts.proprietaire>