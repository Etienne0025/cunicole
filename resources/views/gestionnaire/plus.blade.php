<x-layouts.gestionnaire title="Plus">
    <div class="space-y-2">
        @foreach ([
            ['Synchronisation', 'gestionnaire.synchronisation.index', 'Saisies faites sans réseau, envoi, refus'],
            ['Ventes', 'gestionnaire.ventes.index', 'Nouvelle vente, reçus, soldes à encaisser, objectifs'],
            ['Dépenses', 'gestionnaire.depenses.index', 'Paille, désinfectant, petit matériel...'],
            ['Alimentation et stocks', 'gestionnaire.alimentation.index', 'Distribution du jour, réception des sacs, stocks'],
            ['Santé et soins', 'gestionnaire.sante.index', 'Vaccins, traitements, animaux bloqués, décès'],
            ['Bâtiments', 'gestionnaire.batiments.index', 'Créer et renommer les bâtiments'],
        ] as [$libelle, $route, $aide])
            <a href="{{ route($route) }}" class="block rounded-lg bg-white p-4 shadow">
                <div class="font-semibold">{{ $libelle }}</div>
                <div class="text-xs text-gray-500">{{ $aide }}</div>
            </a>
        @endforeach
    </div>
</x-layouts.gestionnaire>