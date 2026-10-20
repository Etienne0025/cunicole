<x-layouts.proprietaire :title="'Modifier '.$utilisateur->nom_complet">
    <a href="{{ route('proprietaire.utilisateurs.index') }}" class="mb-4 inline-block text-sm text-blue-600 underline">← Tous les comptes</a>

    <form method="POST" action="{{ route('proprietaire.utilisateurs.update', $utilisateur) }}" class="max-w-2xl space-y-4 rounded-lg bg-white p-5 shadow">
        @csrf
        @method('PUT')
        @include('proprietaire.utilisateurs._champs')

        <x-primary-button>Enregistrer</x-primary-button>
    </form>
</x-layouts.proprietaire>