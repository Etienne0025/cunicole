<x-layouts.proprietaire :title="'Prolificité de '.$femelle->matricule">
    <a href="{{ route('proprietaire.reproduction') }}" class="mb-4 inline-block text-sm text-blue-600 underline">← Retour</a>

    @if ($portees->isEmpty())
        <p class="rounded bg-white p-6 text-gray-500 shadow">Aucune mise bas enregistrée pour cette femelle.</p>
    @else
        <div class="mb-6 rounded-lg bg-white p-5 shadow">
            <canvas id="graphique" height="110"></canvas>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Mise bas</th>
                        <th class="px-4 py-2">Mâle</th>
                        <th class="px-4 py-2">Vivants</th>
                        <th class="px-4 py-2">Morts-nés</th>
                        <th class="px-4 py-2">Sevrés</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($portees as $portee)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $portee->date_mise_bas->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ $portee->saillie->male->matricule }}</td>
                            <td class="px-4 py-2">{{ $portee->nes_vivants }}</td>
                            <td class="px-4 py-2">{{ $portee->morts_nes }}</td>
                            <td class="px-4 py-2">{{ $portee->sevres ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new Chart(document.getElementById('graphique'), {
                        type: 'line',
                        data: {
                            labels: @json($portees->map(fn ($p) => $p->date_mise_bas->format('d/m/Y'))->values()),
                            datasets: [
                                { label: 'Nés vivants', data: @json($portees->pluck('nes_vivants')->values()), tension: 0.3 },
                                { label: 'Morts-nés', data: @json($portees->pluck('morts_nes')->values()), tension: 0.3 },
                            ],
                        },
                        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                    });
                });
            </script>
        @endpush
    @endif
</x-layouts.proprietaire>