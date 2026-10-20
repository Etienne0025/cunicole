<x-layouts.gestionnaire title="Saillies">
    <a href="{{ route('gestionnaire.saillies.create') }}" class="mb-3 block rounded bg-emerald-600 py-3 text-center text-white">+ Nouvelle saillie</a>

    <form method="GET" class="mb-3">
        <select name="statut" onchange="this.form.submit()" class="block w-full rounded border-gray-300 text-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $statut)
                <option value="{{ $statut->value }}" @selected(request('statut') === $statut->value)>{{ $statut->libelle() }}</option>
            @endforeach
        </select>
    </form>

    <div class="space-y-2">
        @forelse ($saillies as $saillie)
            <a href="{{ route('gestionnaire.saillies.show', $saillie) }}" class="flex items-center justify-between rounded-lg bg-white p-3 shadow">
                <div>
                    <div class="font-semibold">{{ $saillie->femelle->matricule }} × {{ $saillie->male->matricule }}</div>
                    <div class="text-xs text-gray-500">{{ $saillie->date_saillie->format('d/m/Y') }}</div>
                </div>
                <x-badge-saillie :saillie="$saillie" />
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucune saillie enregistrée.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $saillies->links() }}</div>
</x-layouts.gestionnaire>