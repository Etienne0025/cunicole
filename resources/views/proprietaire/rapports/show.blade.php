@use('App\Enums\StatutTache')

@php
    $faites = $taches->filter(fn ($t) => $t->statut === StatutTache::Faite);
    $nonFaites = $taches->filter(fn ($t) => $t->statut === StatutTache::NonFaite);
    $ouvertes = $taches->filter(fn ($t) => $t->statut === StatutTache::AFaire);
@endphp

<x-layouts.proprietaire :title="'Rapport du '.$jour->format('d/m/Y')">
    <a href="{{ route('proprietaire.rapports.index') }}" class="mb-4 inline-block text-sm text-blue-600 underline">← Tous les rapports</a>

    @unless ($cloture)
        <div class="mb-4 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Journée non clôturée : ce rapport est provisoire.
        </div>
    @endunless

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        @foreach ([
            ['Tâches', $progression['total']],
            ['Faites', $progression['faites']],
            ['Non faites ou restantes', $progression['non_faites'] + $progression['restantes']],
            ['Taux de réussite', $progression['pourcentage'] !== null ? $progression['pourcentage'].' %' : '-'],
        ] as [$libelle, $valeur])
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">{{ $libelle }}</div>
                <div class="mt-1 text-3xl font-bold">{{ $valeur }}</div>
            </div>
        @endforeach
    </div>

    @if ($cloture)
        <div class="mb-6 rounded-lg bg-white p-5 shadow">
            <p class="text-sm text-gray-600">
                Clôturée le {{ $cloture->cloturee_le->format('d/m/Y à H:i') }} par {{ $cloture->utilisateur?->nom_complet ?? '-' }}.
            </p>
            @if ($cloture->commentaire)
                <p class="mt-2">{{ $cloture->commentaire }}</p>
            @endif
        </div>
    @endif

    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Tâches non faites et justifications</h2>
        <table class="mt-2 min-w-full text-sm">
            <tbody>
                @forelse ($nonFaites->concat($ouvertes) as $tache)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $tache->titre }}</td>
                        <td class="px-4 py-2 text-red-700">{{ $tache->justification ?? 'Pas encore justifiée' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-500">Aucune tâche non faite.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Tâches réalisées</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Tâche</th>
                    <th class="px-4 py-2">Heure</th>
                    <th class="px-4 py-2">Fait par</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($faites->sortBy('faite_le') as $tache)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $tache->titre }}</td>
                        <td class="px-4 py-2">{{ $tache->faite_le->format('H:i') }}</td>
                        <td class="px-4 py-2">{{ $tache->faitePar?->nom_complet ?? ($tache->automatique ? 'Automatique' : '-') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Aucune tâche réalisée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>