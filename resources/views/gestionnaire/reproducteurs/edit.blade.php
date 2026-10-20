<x-layouts.gestionnaire :title="'Modifier '.$reproducteur->matricule">
    <form method="POST" action="{{ route('gestionnaire.reproducteurs.update', $reproducteur) }}" class="space-y-4 rounded-lg bg-white p-4 shadow">
        @csrf @method('PUT')
        @include('gestionnaire.reproducteurs._formulaire')
        <x-primary-button>Enregistrer</x-primary-button>
    </form>
</x-layouts.gestionnaire>