<x-layouts.gestionnaire title="Lots d'engraissement">
    <form method="GET" class="mb-3">
        <select name="statut" onchange="this.form.submit()" class="block w-full rounded border-gray-300 text-sm">
            <option value="en_cours" @selected($statutChoisi === 'en_cours')>Lots en cours</option>
            <option value="termine" @selected($statutChoisi === 'termine')>Lots terminés</option>
            <option value="tous" @selected($statutChoisi === 'tous')>Tous les lots</option>
        </select>
    </form>

    <div class="space-y-2">
        @forelse ($lots as $lot)
            <a href="{{ route('gestionnaire.lots.show', $lot) }}" class="block rounded-lg bg-white p-3 shadow">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ $lot->code }}</span>
                    <span class="text-xs text-gray-500">cage {{ $lot->cage?->code ?? '-' }}</span>
                </div>
                <div class="mt-1 text-sm text-gray-600">
                    {{ $lot->effectif_actuel }} / {{ $lot->effectif_initial }} lapins
                    · {{ $lot->ageEnJours() ?? '?' }} jours
                    @if ($lot->dernierePesee)
                        · {{ number_format($lot->dernierePesee->poids_moyen_kg, 2, ',', ' ') }} kg en moyenne
                    @endif
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucun lot. Les lots sont créés au sevrage d'une portée.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $lots->links() }}</div>
</x-layouts.gestionnaire>