<x-layouts.proprietaire title="Reproduction">

    @if ($alerteEchec)
        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800">
            <strong>Alerte :</strong> le taux d'échec des saillies est de {{ $tauxEchec }} % sur les 90 derniers jours
            (seuil : {{ $seuil }} %).
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Naissances du mois</div>
            <div class="mt-1 text-3xl font-bold">
                {{ $naissances }}@if ($objectif !== null)<span class="text-base font-normal text-gray-500"> / {{ $objectif }}</span>@endif
            </div>
            @if ($objectif)
                <div class="mt-2 h-2 rounded bg-gray-200">
                    <div class="h-2 rounded bg-emerald-500" style="width: {{ min(100, round($naissances / $objectif * 100)) }}%"></div>
                </div>
            @endif
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Mises bas attendues (7 jours)</div>
            <div class="mt-1 text-3xl font-bold">{{ $misesBasAttendues->count() }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Taux d'échec des saillies (90 j)</div>
            <div class="mt-1 text-3xl font-bold {{ $alerteEchec ? 'text-red-600' : '' }}">
                {{ $tauxEchec !== null ? $tauxEchec.' %' : '-' }}
            </div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Saillies en cours</div>
            <div class="mt-1 text-3xl font-bold">{{ $saillesEnCours }}</div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('proprietaire.reproduction.objectif') }}" class="rounded-lg bg-white p-5 shadow">
            @csrf
            <h2 class="mb-3 font-semibold">Objectif de naissances de {{ today()->translatedFormat('F Y') }}</h2>
            <div class="flex gap-2">
                <input type="number" name="objectif" min="0" max="5000" value="{{ old('objectif', $objectif) }}"
                       class="w-40 rounded border-gray-300" required>
                <x-primary-button>Enregistrer</x-primary-button>
            </div>
            <x-input-error :messages="$errors->get('objectif')" class="mt-1" />
        </form>

        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Mises bas attendues cette semaine</h2>
            @forelse ($misesBasAttendues as $saillie)
                <div class="flex justify-between border-b py-1 text-sm last:border-0">
                    <span>{{ $saillie->femelle->matricule }}</span>
                    <span class="text-gray-500">prévue le {{ $saillie->datePrevue('mise_bas')->format('d/m/Y') }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aucune mise bas attendue.</p>
            @endforelse
        </div>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Prolificité par femelle</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Femelle</th>
                    <th class="px-4 py-2">Portées</th>
                    <th class="px-4 py-2">Taille moyenne (vivants)</th>
                    <th class="px-4 py-2">Taux de viabilité</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($prolificite as $ligne)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $ligne->matricule }}</td>
                        <td class="px-4 py-2">{{ $ligne->nb_portees }}</td>
                        <td class="px-4 py-2">{{ number_format($ligne->moyenne, 1, ',', ' ') }}</td>
                        <td class="px-4 py-2">{{ number_format($ligne->vivants / max(1, $ligne->vivants + $ligne->morts_nes) * 100, 1, ',', ' ') }} %</td>
                        <td class="px-4 py-2 text-right">
                            <a class="text-blue-600 underline" href="{{ route('proprietaire.reproduction.femelle', $ligne->id) }}">Courbe</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune mise bas enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Registre de consanguinité (saillies bloquées)</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Femelle</th>
                    <th class="px-4 py-2">Mâle</th>
                    <th class="px-4 py-2">Lien</th>
                    <th class="px-4 py-2">Saisi par</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($blocages as $blocage)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $blocage->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2">{{ $blocage->femelle->matricule }}</td>
                        <td class="px-4 py-2">{{ $blocage->male->matricule }}</td>
                        <td class="px-4 py-2">{{ $blocage->lien }}</td>
                        <td class="px-4 py-2">{{ $blocage->utilisateur?->nom_complet ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucune saillie bloquée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>