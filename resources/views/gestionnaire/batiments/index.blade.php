<x-layouts.gestionnaire title="Bâtiments">
    <form method="POST" action="{{ route('gestionnaire.batiments.store') }}" class="mb-4 space-y-3 rounded-lg bg-white p-4 shadow">
        @csrf
        <div>
            <x-input-label for="nom" value="Nom du nouveau bâtiment" />
            <x-text-input id="nom" name="nom" class="mt-1 block w-full" :value="old('nom')" required />
            <x-input-error :messages="$errors->get('nom')" class="mt-1" />
        </div>
        <x-primary-button>Ajouter</x-primary-button>
    </form>

    <div class="space-y-2">
        @forelse ($batiments as $batiment)
            <div class="flex items-center justify-between rounded-lg bg-white p-4 shadow" x-data="{ edition: false }">
                <div x-show="!edition">
                    <div class="font-medium">{{ $batiment->nom }}</div>
                    <div class="text-xs text-gray-500">{{ $batiment->cages_count }} cage(s)</div>
                </div>

                <form x-show="edition" x-cloak method="POST" action="{{ route('gestionnaire.batiments.update', $batiment) }}" class="flex gap-2">
                    @csrf @method('PATCH')
                    <input name="nom" value="{{ $batiment->nom }}" class="rounded border-gray-300 text-sm" required>
                    <button class="text-sm text-emerald-700">OK</button>
                </form>

                <div class="flex items-center gap-3 text-sm">
                    <button type="button" @click="edition = !edition" class="text-blue-600">Renommer</button>
                    <form method="POST" action="{{ route('gestionnaire.batiments.destroy', $batiment) }}"
                          onsubmit="return confirm('Supprimer ce bâtiment ?')">
                        @csrf @method('DELETE')
                        <button class="text-red-600">Supprimer</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun bâtiment. Ajoutez le premier ci-dessus.</p>
        @endforelse
    </div>
</x-layouts.gestionnaire>