@php
    $dateNaissance = old('date_naissance', $reproducteur?->date_naissance?->format('Y-m-d'));
    $dateEntree = old('date_entree', $reproducteur?->date_entree?->format('Y-m-d'));
@endphp

<div>
    <x-input-label for="matricule" value="Matricule (code de tatouage)" />
    <x-text-input id="matricule" name="matricule" class="mt-1 block w-full uppercase"
                  :value="old('matricule', $reproducteur?->matricule)" maxlength="30" required />
    <x-input-error :messages="$errors->get('matricule')" class="mt-1" />
</div>

<div>
    <x-input-label for="nom" value="Nom (facultatif)" />
    <x-text-input id="nom" name="nom" class="mt-1 block w-full" :value="old('nom', $reproducteur?->nom)" />
    <x-input-error :messages="$errors->get('nom')" class="mt-1" />
</div>

<div>
    <x-input-label for="sexe" value="Sexe" />
    @if ($reproducteur)
        <p class="mt-1 text-sm">{{ $reproducteur->sexe->libelle() }} <span class="text-xs text-gray-500">(non modifiable)</span></p>
    @else
        <select id="sexe" name="sexe" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            <option value="">Choisir...</option>
            @foreach ($sexes as $sexe)
                <option value="{{ $sexe->value }}" @selected(old('sexe') === $sexe->value)>{{ $sexe->libelle() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('sexe')" class="mt-1" />
    @endif
</div>

<div>
    <x-input-label for="race" value="Race" />
    <x-text-input id="race" name="race" class="mt-1 block w-full" :value="old('race', $reproducteur?->race)" />
    <x-input-error :messages="$errors->get('race')" class="mt-1" />
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <x-input-label for="date_naissance" value="Date de naissance" />
        <x-text-input id="date_naissance" name="date_naissance" type="date" class="mt-1 block w-full" :value="$dateNaissance" required />
        <x-input-error :messages="$errors->get('date_naissance')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="date_entree" value="Entrée dans le cheptel" />
        <x-text-input id="date_entree" name="date_entree" type="date" class="mt-1 block w-full" :value="$dateEntree" required />
        <x-input-error :messages="$errors->get('date_entree')" class="mt-1" />
    </div>
</div>

<div>
    <x-input-label for="origine" value="Origine" />
    <select id="origine" name="origine" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        @foreach ($origines as $origine)
            <option value="{{ $origine->value }}" @selected(old('origine', $reproducteur?->origine?->value) === $origine->value)>{{ $origine->libelle() }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('origine')" class="mt-1" />
</div>

<div class="grid grid-cols-2 gap-3">
    <div>
        <x-input-label for="mere_id" value="Mère" />
        <select id="mere_id" name="mere_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Inconnue</option>
            @foreach ($meres as $mere)
                <option value="{{ $mere->id }}" @selected(old('mere_id', $reproducteur?->mere_id) == $mere->id)>{{ $mere->matricule }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('mere_id')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="pere_id" value="Père" />
        <select id="pere_id" name="pere_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            <option value="">Inconnu</option>
            @foreach ($peres as $pere)
                <option value="{{ $pere->id }}" @selected(old('pere_id', $reproducteur?->pere_id) == $pere->id)>{{ $pere->matricule }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('pere_id')" class="mt-1" />
    </div>
</div>

<div>
    <x-input-label for="notes" value="Notes" />
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('notes', $reproducteur?->notes) }}</textarea>
</div>