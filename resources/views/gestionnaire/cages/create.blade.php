<x-layouts.gestionnaire title="Nouvelles cages">
    @if ($batiments->isEmpty())
        <p class="rounded bg-amber-100 p-3 text-amber-800">
            Créez d'abord un <a href="{{ route('gestionnaire.batiments.index') }}" class="underline">bâtiment</a>.
        </p>
    @else
        <form method="POST" action="{{ route('gestionnaire.cages.store') }}" class="space-y-4 rounded-lg bg-white p-4 shadow">
            @csrf

            <div>
                <x-input-label for="batiment_id" value="Bâtiment" />
                <select id="batiment_id" name="batiment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    @foreach ($batiments as $batiment)
                        <option value="{{ $batiment->id }}" @selected(old('batiment_id') == $batiment->id)>{{ $batiment->nom }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('batiment_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="prefixe" value="Préfixe du code (ex : A)" />
                    <x-text-input id="prefixe" name="prefixe" class="mt-1 block w-full uppercase" :value="old('prefixe')" maxlength="10" required />
                    <x-input-error :messages="$errors->get('prefixe')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="nombre" value="Nombre de cages" />
                    <x-text-input id="nombre" name="nombre" type="number" min="1" max="100" class="mt-1 block w-full" :value="old('nombre', 1)" required />
                    <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="type" value="Type de cage" />
                <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->libelle() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="capacite" value="Capacité (nombre d'animaux)" />
                <x-text-input id="capacite" name="capacite" type="number" min="1" max="50" class="mt-1 block w-full" :value="old('capacite', 1)" required />
                <x-input-error :messages="$errors->get('capacite')" class="mt-1" />
            </div>

            <p class="text-xs text-gray-500">Les codes sont générés automatiquement : A-001, A-002... en continuant la numérotation existante.</p>

            <x-primary-button>Créer les cages</x-primary-button>
        </form>
    @endif
</x-layouts.gestionnaire>