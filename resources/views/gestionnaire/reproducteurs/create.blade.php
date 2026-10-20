<x-layouts.gestionnaire title="Nouveau reproducteur">
    <form method="POST" action="{{ route('gestionnaire.reproducteurs.store') }}" class="space-y-4 rounded-lg bg-white p-4 shadow">
        @csrf
        @include('gestionnaire.reproducteurs._formulaire')
        <x-primary-button>Enregistrer</x-primary-button>
    </form>
</x-layouts.gestionnaire>