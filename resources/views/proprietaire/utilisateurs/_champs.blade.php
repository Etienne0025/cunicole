@php $moi = $utilisateur !== null && $utilisateur->is(auth()->user()); @endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <x-input-label for="nom" value="Nom" />
        <x-text-input id="nom" name="nom" class="mt-1 block w-full" :value="old('nom', $utilisateur?->nom)" maxlength="100" required />
        <x-input-error :messages="$errors->get('nom')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="prenom" value="Prénom" />
        <x-text-input id="prenom" name="prenom" class="mt-1 block w-full" :value="old('prenom', $utilisateur?->prenom)" maxlength="100" required />
        <x-input-error :messages="$errors->get('prenom')" class="mt-1" />
    </div>
</div>

<div>
    <x-input-label for="email" value="Adresse email (sert d'identifiant de connexion)" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $utilisateur?->email)" maxlength="255" required />
    <x-input-error :messages="$errors->get('email')" class="mt-1" />
</div>

<div>
    <x-input-label for="telephone" value="Téléphone (facultatif)" />
    <x-text-input id="telephone" name="telephone" class="mt-1 block w-full" :value="old('telephone', $utilisateur?->telephone)" maxlength="30" />
    <x-input-error :messages="$errors->get('telephone')" class="mt-1" />
</div>

<div>
    <x-input-label for="role" value="Rôle" />

    @if ($moi)
        <input type="hidden" name="role" value="{{ $utilisateur->role->value }}">
        <p class="mt-1 text-sm">{{ $utilisateur->role->libelle() }} <span class="text-xs text-gray-500">(vous ne pouvez pas modifier votre propre rôle)</span></p>
    @else
        <select id="role" name="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected(old('role', $utilisateur?->role?->value ?? 'gestionnaire') === $role->value)>{{ $role->libelle() }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Un propriétaire accède à toutes les données financières et à la gestion des comptes.</p>
    @endif
    <x-input-error :messages="$errors->get('role')" class="mt-1" />
</div>