<x-layouts.gestionnaire :title="'Modifier '.$cage->code">
    <form method="POST" action="{{ route('gestionnaire.cages.update', $cage) }}" class="space-y-4 rounded-lg bg-white p-4 shadow">
        @csrf @method('PUT')

        <div>
            <x-input-label for="batiment_id" value="Bâtiment" />
            <select id="batiment_id" name="batiment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                @foreach ($batiments as $batiment)
                    <option value="{{ $batiment->id }}" @selected(old('batiment_id', $cage->batiment_id) == $batiment->id)>{{ $batiment->nom }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('batiment_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="type" value="Type de cage" />
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $cage->type->value) === $type->value)>{{ $type->libelle() }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <x-input-label for="capacite" value="Capacité" />
            <x-text-input id="capacite" name="capacite" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('capacite', $cage->capacite)" required />
            <x-input-error :messages="$errors->get('capacite')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $cage->notes) }}</textarea>
        </div>

        <x-primary-button>Enregistrer</x-primary-button>
    </form>

    <form method="POST" action="{{ route('gestionnaire.cages.destroy', $cage) }}" class="mt-4"
          onsubmit="return confirm('Supprimer définitivement cette cage ?')">
        @csrf @method('DELETE')
        <button class="w-full rounded border border-red-300 py-3 text-red-600">Supprimer la cage</button>
    </form>
</x-layouts.gestionnaire>