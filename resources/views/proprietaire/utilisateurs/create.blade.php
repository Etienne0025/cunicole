<x-layouts.proprietaire title="Nouveau compte">
    <a href="{{ route('proprietaire.utilisateurs.index') }}" class="mb-4 inline-block text-sm text-blue-600 underline">← Tous les comptes</a>

    <form method="POST" action="{{ route('proprietaire.utilisateurs.store') }}" class="max-w-2xl space-y-4 rounded-lg bg-white p-5 shadow">
        @csrf
        @include('proprietaire.utilisateurs._champs')

        <p class="rounded bg-gray-50 p-3 text-xs text-gray-600">
            Un mot de passe provisoire sera généré et affiché une seule fois après la création.
            La personne devra choisir son propre mot de passe à sa première connexion.
        </p>

        <x-primary-button>Créer le compte</x-primary-button>
    </form>
</x-layouts.proprietaire>