@php
    $causes = \App\Enums\CauseMortalite::cases();
    $ouvertParDefaut = $errors->hasAny(['cause', 'date_deces', 'nombre']);
@endphp

<div x-data="{ ouvert: {{ $ouvertParDefaut ? 'true' : 'false' }} }" class="rounded-lg border border-red-200 bg-white p-4">
    <button type="button" @click="ouvert = !ouvert" class="w-full text-red-600">{{ $libelle ?? 'Déclarer un décès' }}</button>

    <form x-show="ouvert" x-cloak method="POST" action="{{ route('gestionnaire.sante.deces') }}" class="mt-3 space-y-3">
        @csrf
        <input type="hidden" name="cible_type" value="{{ $type }}">
        <input type="hidden" name="cible_id" value="{{ $cible->id }}">

        @if ($avecNombre)
            <div>
                <x-input-label for="nombre-{{ $type }}" value="Nombre de morts" />
                <input id="nombre-{{ $type }}" type="number" name="nombre" min="1" max="{{ $max }}" value="{{ old('nombre', 1) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
            </div>
        @endif

        <div>
            <x-input-label for="cause-{{ $type }}" value="Cause suspectée" />
            <select id="cause-{{ $type }}" name="cause" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                <option value="">Choisir...</option>
                @foreach ($causes as $cause)
                    <option value="{{ $cause->value }}" @selected(old('cause') === $cause->value)>{{ $cause->libelle() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('cause')" class="mt-1" />
        </div>

        <input name="precisions" value="{{ old('precisions') }}" maxlength="200" placeholder="Précisions (facultatif)"
               class="block w-full rounded-md border-gray-300 text-sm shadow-sm">

        <div>
            <x-input-label for="date-deces-{{ $type }}" value="Date du décès" />
            <input id="date-deces-{{ $type }}" type="date" name="date_deces" value="{{ old('date_deces', today()->toDateString()) }}"
                   max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            <x-input-error :messages="$errors->get('date_deces')" class="mt-1" />
        </div>

        <button class="w-full rounded bg-red-600 py-3 text-white" onclick="return confirm('Confirmer ce décès ? Cette action est définitive.')">
            Confirmer le décès
        </button>
    </form>
</div>