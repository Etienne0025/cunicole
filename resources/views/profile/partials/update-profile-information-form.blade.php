<div>
    <x-input-label for="nom" value="Nom" />
    <x-text-input id="nom" name="nom" type="text" class="mt-1 block w-full"
                  :value="old('nom', $user->nom)" required autofocus autocomplete="family-name" />
    <x-input-error class="mt-2" :messages="$errors->get('nom')" />
</div>

<div>
    <x-input-label for="prenom" value="Prénom" />
    <x-text-input id="prenom" name="prenom" type="text" class="mt-1 block w-full"
                  :value="old('prenom', $user->prenom)" required autocomplete="given-name" />
    <x-input-error class="mt-2" :messages="$errors->get('prenom')" />
</div>