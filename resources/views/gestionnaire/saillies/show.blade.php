@use('App\Enums\StatutSaillie')

@php
    $jalons = [
        'palpation' => ['Palpation', $saillie->date_palpation],
        'boite_nids' => ['Boîte à nids', $saillie->boite_nids_le],
        'mise_bas' => ['Mise bas', $saillie->portee?->date_mise_bas],
        'sevrage' => ['Sevrage', $saillie->portee?->date_sevrage],
    ];
   if (in_array($saillie->statut, [StatutSaillie::NonGestante, StatutSaillie::Interrompue], true)) {
    $jalons = array_slice($jalons, 0, 1, true);
}
@endphp

<x-layouts.gestionnaire title="Saillie">
    <div class="space-y-4">

        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-lg font-bold">
                        <a class="underline" href="{{ route('gestionnaire.reproducteurs.show', $saillie->femelle) }}">{{ $saillie->femelle->matricule }}</a>
                        ×
                        <a class="underline" href="{{ route('gestionnaire.reproducteurs.show', $saillie->male) }}">{{ $saillie->male->matricule }}</a>
                    </div>
                    <div class="text-sm text-gray-500">Saillie du {{ $saillie->date_saillie->format('d/m/Y') }}</div>
                </div>
                <x-badge-saillie :saillie="$saillie" />
            </div>
            @if ($saillie->notes)<p class="mt-2 text-sm">{{ $saillie->notes }}</p>@endif
        </div>

        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="mb-2 font-semibold">Calendrier</h2>
            @foreach ($jalons as $cle => [$libelle, $realise])
                <div class="flex justify-between border-b py-2 text-sm last:border-0">
                    <span>{{ $libelle }} <span class="text-xs text-gray-500">(J+{{ config("cunicole.reproduction.jours_$cle") }})</span></span>
                    @if ($realise)
                        <span class="text-emerald-700">✔ le {{ $realise->format('d/m/Y') }}</span>
                    @else
                        <span class="text-gray-500">prévu le {{ $saillie->datePrevue($cle)->format('d/m/Y') }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Palpation --}}
        @if ($saillie->statut === StatutSaillie::EnAttente)
            <form method="POST" action="{{ route('gestionnaire.saillies.palpation', $saillie) }}" class="space-y-3 rounded-lg bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Résultat de la palpation</h2>
                <input type="date" name="date_palpation" value="{{ old('date_palpation', today()->toDateString()) }}"
                       max="{{ today()->toDateString() }}" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                <x-input-error :messages="$errors->get('date_palpation')" />
                <x-input-error :messages="$errors->get('resultat')" />
                <div class="grid grid-cols-2 gap-2">
                    <button name="resultat" value="gestante" class="rounded bg-emerald-600 py-3 text-white">Gestante</button>
                    <button name="resultat" value="non_gestante" class="rounded bg-red-600 py-3 text-white">Non gestante</button>
                </div>
            </form>
        @endif

        {{-- Gestante : boîte à nids et mise bas --}}
        @if ($saillie->statut === StatutSaillie::Gestante)
            @if ($saillie->boite_nids_le === null)
                <form method="POST" action="{{ route('gestionnaire.saillies.boite_nids', $saillie) }}" class="space-y-3 rounded-lg bg-white p-4 shadow">
                    @csrf
                    <h2 class="font-semibold">Boîte à nids</h2>
                    <input type="date" name="boite_nids_le" value="{{ old('boite_nids_le', today()->toDateString()) }}"
                           max="{{ today()->toDateString() }}" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    <x-input-error :messages="$errors->get('boite_nids_le')" />
                    <button class="w-full rounded bg-slate-800 py-3 text-white">Boîte à nids installée</button>
                </form>
            @endif

            <form method="POST" action="{{ route('gestionnaire.saillies.mise_bas', $saillie) }}" class="space-y-3 rounded-lg bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Enregistrer la mise bas</h2>
                <div>
                    <x-input-label for="date_mise_bas" value="Date de mise bas" />
                    <input id="date_mise_bas" type="date" name="date_mise_bas" value="{{ old('date_mise_bas', today()->toDateString()) }}"
                           max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <x-input-error :messages="$errors->get('date_mise_bas')" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="nes_vivants" value="Nés vivants" />
                        <input id="nes_vivants" type="number" name="nes_vivants" min="0" max="30" value="{{ old('nes_vivants', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <x-input-error :messages="$errors->get('nes_vivants')" />
                    </div>
                    <div>
                        <x-input-label for="morts_nes" value="Morts-nés" />
                        <input id="morts_nes" type="number" name="morts_nes" min="0" max="30" value="{{ old('morts_nes', 0) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <x-input-error :messages="$errors->get('morts_nes')" />
                    </div>
                </div>
                <button class="w-full rounded bg-emerald-600 py-3 text-white">Enregistrer la mise bas</button>
            </form>
        @endif

        {{-- Mise bas faite : portée et sevrage --}}
        @if ($saillie->portee)
            <div class="rounded-lg bg-white p-4 shadow">
                <h2 class="mb-2 font-semibold">Portée</h2>
                <dl class="grid grid-cols-4 gap-2 text-center text-sm">
                    <div><dt class="text-gray-500">Vivants</dt><dd class="text-xl font-bold">{{ $saillie->portee->nes_vivants }}</dd></div>
                    <div><dt class="text-gray-500">Morts-nés</dt><dd class="text-xl font-bold">{{ $saillie->portee->morts_nes }}</dd></div>
                    <div><dt class="text-gray-500">Sevrés</dt><dd class="text-xl font-bold">{{ $saillie->portee->sevres ?? '-' }}</dd></div>
                    <div><dt class="text-gray-500">Morts avant sevrage</dt><dd class="text-xl font-bold">{{ $saillie->portee->morts_avant_sevrage }}</dd></div>
                </dl>
                @if ($saillie->portee->lot)
                    <p class="mt-3 rounded bg-emerald-50 p-2 text-sm">
                        Lot <strong>{{ $saillie->portee->lot->code }}</strong> en cage
                        {{ $saillie->portee->lot->cage?->code ?? '-' }}
                    </p>
                @endif
            </div>
        @endif

        @if ($saillie->statut === StatutSaillie::MiseBas)
    @include('gestionnaire.sante._deces', [
        'type' => 'portee',
        'cible' => $saillie->portee,
        'avecNombre' => true,
        'max' => $saillie->portee->vivantsActuels(),
        'libelle' => 'Déclarer un décès de lapereaux',
    ])
@endif

        @if ($saillie->statut === StatutSaillie::MiseBas)
            <form method="POST" action="{{ route('gestionnaire.saillies.sevrage', $saillie) }}" class="space-y-3 rounded-lg bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Sevrage et création du lot d'engraissement</h2>
                <div>
                    <x-input-label for="date_sevrage" value="Date de sevrage" />
                    <input id="date_sevrage" type="date" name="date_sevrage" value="{{ old('date_sevrage', today()->toDateString()) }}"
                           max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <x-input-error :messages="$errors->get('date_sevrage')" />
                </div>
                <div>
                    <x-input-label for="nombre_sevres" value="Nombre de lapereaux sevrés" />
                    <input id="nombre_sevres" type="number" name="nombre_sevres" min="0" max="30"
                           value="{{old('nombre_sevres', $saillie->portee->vivantsActuels())}}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    <x-input-error :messages="$errors->get('nombre_sevres')" />
                </div>
                <div>
                    <x-input-label for="cage_id" value="Cage d'engraissement" />
                    <select id="cage_id" name="cage_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choisir...</option>
                        @foreach ($cagesEngraissement as $cage)
                            <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>
                                {{ $cage->code }} · {{ $cage->batiment->nom }} ({{ $cage->capacite }} places)
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cage_id')" />
                    @if ($cagesEngraissement->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">Aucune cage d'engraissement libre. Créez ou activez-en une.</p>
                    @endif
                </div>
                <button class="w-full rounded bg-emerald-600 py-3 text-white" onclick="return confirm('Confirmer le sevrage ?')">
                    Valider le sevrage
                </button>
            </form>
        @endif
    </div>
</x-layouts.gestionnaire>