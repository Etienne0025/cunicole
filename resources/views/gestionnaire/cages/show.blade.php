@use('App\Enums\StatutCage')

<x-layouts.gestionnaire :title="'Cage '.$cage->code">
    <div class="space-y-4">
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xl font-bold">{{ $cage->code }}</div>
                    <div class="text-sm text-gray-500">{{ $cage->batiment->nom }}</div>
                </div>
                <x-badge-statut :cage="$cage" />
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div><dt class="text-gray-500">Type</dt><dd>{{ $cage->type->libelle() }}</dd></div>
                <div><dt class="text-gray-500">Capacité</dt><dd>{{ $cage->capacite }} animal(aux)</dd></div>
                <div><dt class="text-gray-500">Activée le</dt><dd>{{ $cage->activee_le?->format('d/m/Y') ?? 'Pas encore' }}</dd></div>
            </dl>

            @if ($cage->notes)
                <p class="mt-3 text-sm">{{ $cage->notes }}</p>
            @endif
        </div>

        <div class="rounded-lg bg-white p-4 text-center shadow">
            <div class="mx-auto w-40">{!! $qrSvg !!}</div>
            <div class="mt-2 text-xs text-gray-500">QR code de la cage</div>
        </div>

        <div class="space-y-2">
            @if (! $cage->estActivee())
                <form method="POST" action="{{ route('gestionnaire.cages.activer', $cage) }}">
                    @csrf
                    <button class="w-full rounded bg-emerald-600 py-3 text-white">Activer la cage (QR fixé)</button>
                </form>
            @endif

            @if ($cage->estActivee() && $cage->statut === StatutCage::Vide)
                <form method="POST" action="{{ route('gestionnaire.cages.statut', $cage) }}">
                    @csrf
                    <input type="hidden" name="statut" value="a_nettoyer">
                    <button class="w-full rounded bg-amber-500 py-3 text-white">Marquer « à nettoyer »</button>
                </form>
            @endif

            @if ($cage->statut === StatutCage::ANettoyer)
                <form method="POST" action="{{ route('gestionnaire.cages.statut', $cage) }}">
                    @csrf
                    <input type="hidden" name="statut" value="vide">
                    <button class="w-full rounded bg-emerald-600 py-3 text-white">Cage nettoyée (remettre à vide)</button>
                </form>
            @endif

            <a href="{{ route('gestionnaire.cages.edit', $cage) }}" class="block rounded border bg-white py-3 text-center">Modifier</a>
        </div>

       <div class="rounded-lg bg-white p-4 shadow">
    <div class="mb-2 flex items-center justify-between">
        <h2 class="font-semibold">Contenu de la cage</h2>
        <span class="text-xs text-gray-500">{{ $cage->reproducteurs->count() }} / {{ $cage->capacite }}</span>
    </div>

   @forelse ($cage->reproducteurs as $occupant)
    <a href="{{ route('gestionnaire.reproducteurs.show', $occupant) }}"
       class="flex justify-between border-b py-2 text-sm last:border-0">
        <span>{{ $occupant->matricule }} @if ($occupant->nom) <span class="text-gray-500">({{ $occupant->nom }})</span> @endif</span>
        <span class="text-gray-500">{{ $occupant->sexe->libelle() }}</span>
    </a>
@empty
    @if ($cage->lots->isEmpty())
        <p class="text-sm text-gray-500">Aucun animal dans cette cage.</p>
    @endif
@endforelse

@foreach ($cage->lots as $lot)
    <div class="mt-2 rounded bg-emerald-50 p-2 text-sm">
        Lot <strong>{{ $lot->code }}</strong> · {{ $lot->effectif_actuel }} lapins
    </div>
@endforeach
</div>
    </div>
</x-layouts.gestionnaire>