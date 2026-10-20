@php $c = config('cunicole.reproduction'); @endphp

<x-layouts.gestionnaire title="Élevage">
    <div class="mb-4 grid grid-cols-2 gap-2">
        <a href="{{ route('gestionnaire.saillies.create') }}" class="rounded bg-emerald-600 py-3 text-center text-white">+ Saillie</a>
        <a href="{{ route('gestionnaire.saillies.index') }}" class="rounded border bg-white py-3 text-center">Toutes les saillies</a>
        <a href="{{ route('gestionnaire.reproducteurs.index') }}" class="col-span-2 rounded border bg-white py-3 text-center">Reproducteurs</a>
        <a href="{{ route('gestionnaire.lots.index') }}" class="col-span-2 rounded border bg-white py-3 text-center">Lots d'engraissement</a>
    </div>

    <p class="mb-3 text-sm text-gray-600">
        Planning du {{ today()->translatedFormat('l d F Y') }} · {{ $gestantes }} femelle(s) gestante(s)
    </p>

    <div class="space-y-3">
        @include('gestionnaire.elevage._section', ['titre' => 'Palpations à faire (J+'.$c['jours_palpation'].')', 'saillies' => $planning['palpations'], 'jalon' => 'palpation'])
        @include('gestionnaire.elevage._section', ['titre' => 'Boîtes à nids à installer (J+'.$c['jours_boite_nids'].')', 'saillies' => $planning['boites_nids'], 'jalon' => 'boite_nids'])
        @include('gestionnaire.elevage._section', ['titre' => 'Mises bas à surveiller', 'saillies' => $planning['mises_bas'], 'jalon' => 'mise_bas'])
        @include('gestionnaire.elevage._section', ['titre' => 'Sevrages à faire (J+'.$c['jours_sevrage'].')', 'saillies' => $planning['sevrages'], 'jalon' => 'sevrage'])
    </div>
</x-layouts.gestionnaire>