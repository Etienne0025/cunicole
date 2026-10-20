<x-layouts.proprietaire title="Rapports journaliers">

    @foreach ($joursNonClotures as $jourPasse)
        <a href="{{ route('proprietaire.rapports.show', $jourPasse) }}"
           class="mb-3 block rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            La journée du {{ \Illuminate\Support\Carbon::parse($jourPasse)->format('d/m/Y') }} n'est pas clôturée (rapport provisoire).
        </a>
    @endforeach

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Clôturée par</th>
                    <th class="px-4 py-2">Tâches</th>
                    <th class="px-4 py-2">Faites</th>
                    <th class="px-4 py-2">Non faites</th>
                    <th class="px-4 py-2">Réussite</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clotures as $cloture)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $cloture->date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $cloture->utilisateur?->nom_complet ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $cloture->total }}</td>
                        <td class="px-4 py-2">{{ $cloture->faites }}</td>
                        <td class="px-4 py-2 {{ $cloture->non_faites > 0 ? 'text-red-600' : '' }}">{{ $cloture->non_faites }}</td>
                        <td class="px-4 py-2">{{ $cloture->tauxReussite() !== null ? $cloture->tauxReussite().' %' : '-' }}</td>
                        <td class="px-4 py-2 text-right">
                            <a class="text-blue-600 underline" href="{{ route('proprietaire.rapports.show', $cloture->date->toDateString()) }}">Voir</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Aucune journée clôturée pour le moment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clotures->links() }}</div>
</x-layouts.proprietaire>