<div class="rounded-lg bg-white p-4 shadow">
    <h2 class="mb-2 flex items-center justify-between font-semibold">
        {{ $titre }}
        <span class="rounded-full bg-gray-100 px-2 text-sm">{{ $saillies->count() }}</span>
    </h2>

    @forelse ($saillies as $saillie)
        @php
            $prevue = $saillie->datePrevue($jalon);
            $retard = $saillie->joursDeRetard($jalon);
        @endphp
        <a href="{{ route('gestionnaire.saillies.show', $saillie) }}"
           class="flex items-center justify-between border-b py-2 text-sm last:border-0">
            <span>
                <strong>{{ $saillie->femelle->matricule }}</strong>
                @if ($saillie->femelle->cage) · cage {{ $saillie->femelle->cage->code }} @endif
            </span>
            <span class="text-xs {{ $retard > 0 ? 'text-red-600' : 'text-gray-500' }}">
                {{ $retard > 0 ? "en retard de {$retard} j" : ($prevue->isFuture() ? 'prévue le '.$prevue->format('d/m') : "aujourd'hui") }}
            </span>
        </a>
    @empty
        <p class="text-sm text-gray-500">Rien à faire.</p>
    @endforelse
</div>