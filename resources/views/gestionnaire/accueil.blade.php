<x-layouts.gestionnaire title="Accueil">
    <p class="mb-4">Bonjour {{ auth()->user()->prenom }} 👋</p>

    @foreach ($joursNonClotures as $jourPasse)
        <a href="{{ route('gestionnaire.taches.cloture', ['date' => $jourPasse]) }}"
           class="mb-3 block rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            La journée du {{ \Illuminate\Support\Carbon::parse($jourPasse)->format('d/m/Y') }} n'est pas clôturée. Justifiez les tâches restantes →
        </a>
    @endforeach

    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('gestionnaire.taches.index') }}" class="rounded-lg bg-white p-4 shadow">
            <div class="text-xs text-gray-500">Tâches restantes</div>
            <div class="text-2xl font-bold">
                {{ $progression['restantes'] }}<span class="text-sm font-normal text-gray-500"> / {{ $progression['total'] }}</span>
            </div>
        </a>
        <a href="{{ route('gestionnaire.cages.index', ['statut' => 'a_nettoyer']) }}" class="rounded-lg bg-white p-4 shadow">
            <div class="text-xs text-gray-500">Cages à nettoyer</div>
            <div class="text-2xl font-bold">{{ $cagesANettoyer }}</div>
        </a>
    </div>

    <a href="{{ route('gestionnaire.taches.index') }}" class="mt-4 block rounded bg-emerald-600 py-3 text-center text-white">
        Voir ma checklist du jour
    </a>
</x-layouts.gestionnaire>