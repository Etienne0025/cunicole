<x-layouts.proprietaire :title="'Vente '.$vente->numero">
    <a href="{{ route('proprietaire.ventes.index') }}" class="mb-4 inline-block text-sm text-blue-600 underline">← Toutes les ventes</a>

    <div class="max-w-3xl space-y-4">
        @include('ventes._detail', ['vente' => $vente])

        <a href="{{ route('proprietaire.ventes.recu', $vente) }}" target="_blank" class="block rounded bg-slate-800 py-3 text-center text-white">Voir le reçu (PDF)</a>
    </div>
</x-layouts.proprietaire>