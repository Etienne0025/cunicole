<x-layouts.gestionnaire title="Cages">
    <div class="mb-3 flex gap-2">
        <a href="{{ route('gestionnaire.cages.create') }}" class="rounded bg-emerald-600 px-3 py-2 text-sm text-white">+ Nouvelles cages</a>
        <a href="{{ route('gestionnaire.batiments.index') }}" class="rounded border bg-white px-3 py-2 text-sm">Bâtiments</a>
    </div>

    <form method="GET" class="mb-3 grid grid-cols-2 gap-2">
        <select name="batiment" onchange="this.form.submit()" class="rounded border-gray-300 text-sm">
            <option value="">Tous les bâtiments</option>
            @foreach ($batiments as $batiment)
                <option value="{{ $batiment->id }}" @selected(request('batiment') == $batiment->id)>{{ $batiment->nom }}</option>
            @endforeach
        </select>
        <select name="statut" onchange="this.form.submit()" class="rounded border-gray-300 text-sm">
            <option value="">Tous les statuts</option>
            @foreach ($statuts as $statut)
                <option value="{{ $statut->value }}" @selected(request('statut') === $statut->value)>{{ $statut->libelle() }}</option>
            @endforeach
        </select>
    </form>

    <form method="POST" action="{{ route('gestionnaire.cages.planche') }}" x-data="{ tout: false }">
        @csrf
        <x-input-error :messages="$errors->get('cages')" class="mb-2" />

        <label class="mb-2 flex items-center gap-2 text-sm">
            <input type="checkbox" x-model="tout" @change="$root.querySelectorAll('.case-cage').forEach(c => c.checked = tout)">
            Tout sélectionner
        </label>

        <div class="space-y-2">
            @forelse ($cages as $cage)
                <div class="flex items-center gap-3 rounded-lg bg-white p-3 shadow">
                    <input type="checkbox" name="cages[]" value="{{ $cage->uuid }}" class="case-cage">
                    <a href="{{ route('gestionnaire.cages.show', $cage) }}" class="flex flex-1 items-center justify-between">
                        <div>
                            <div class="font-semibold">{{ $cage->code }}</div>
                            <div class="text-xs text-gray-500">{{ $cage->batiment->nom }} · {{ $cage->type->libelle() }}</div>
                        </div>
                        <x-badge-statut :cage="$cage" />
                    </a>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aucune cage pour le moment.</p>
            @endforelse
        </div>

        @if ($cages->count())
            <button class="mt-4 w-full rounded bg-slate-800 py-3 text-white">
                Télécharger la planche de QR codes (PDF)
            </button>
        @endif
    </form>

    <div class="mt-4">{{ $cages->links() }}</div>
</x-layouts.gestionnaire>