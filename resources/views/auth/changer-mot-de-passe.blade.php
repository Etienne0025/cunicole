<x-guest-layout>
    <p class="mb-4 text-sm text-gray-600">
        Votre compte utilise un mot de passe provisoire. Choisissez maintenant un mot de passe personnel pour continuer.
    </p>

    <form method="POST" action="{{ route('mot-de-passe.mettre_a_jour') }}">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" value="Mot de passe provisoire" />
            <x-text-input id="current_password" class="mt-1 block w-full" type="password" name="current_password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Nouveau mot de passe (8 caractères minimum)" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirmer le nouveau mot de passe" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="mt-6">
            <x-primary-button>Enregistrer et continuer</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button class="text-sm text-gray-600 underline">Se déconnecter</button>
    </form>
</x-guest-layout>