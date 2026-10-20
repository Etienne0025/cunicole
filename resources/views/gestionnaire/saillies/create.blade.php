<x-layouts.gestionnaire title="Nouvelle saillie">
    <form method="POST" action="{{ route('gestionnaire.saillies.store') }}" class="space-y-4 rounded-lg bg-white p-4 shadow">
        @csrf

        <div>
            <x-input-label for="femelle_id" value="Femelle" />
            <select id="femelle_id" name="femelle_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <option value="">Choisir...</option>
                @foreach ($femelles as $femelle)
                    <option value="{{ $femelle->id }}" @selected(old('femelle_id', $femelleChoisie) == $femelle->id)>
                        {{ $femelle->matricule }} @if ($femelle->cage) (cage {{ $femelle->cage->code }}) @endif
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('femelle_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="male_id" value="Mâle" />
            <select id="male_id" name="male_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <option value="">Choisir...</option>
                @foreach ($males as $male)
                    <option value="{{ $male->id }}" @selected(old('male_id') == $male->id)>
                        {{ $male->matricule }} @if ($male->cage) (cage {{ $male->cage->code }}) @endif
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('male_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="date_saillie" value="Date de la saillie" />
            <x-text-input id="date_saillie" name="date_saillie" type="date" class="mt-1 block w-full"
                          :value="old('date_saillie', today()->toDateString())" max="{{ today()->toDateString() }}" required />
            <x-input-error :messages="$errors->get('date_saillie')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes') }}</textarea>
        </div>

        <p class="text-xs text-gray-500">
            La saillie est bloquée automatiquement en cas de risque de consanguinité.
        </p>

        <x-primary-button>Enregistrer la saillie</x-primary-button>
    </form>
</x-layouts.gestionnaire>