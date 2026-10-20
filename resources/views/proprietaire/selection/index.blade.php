<x-layouts.proprietaire title="Sélection et renouvellement">

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        @foreach ([
            ['À valider', $enAttente->count()],
            ['Reproducteurs à renouveler (> '.$ageReforme.' mois)', $ages->count()],
            ['Relève validée (12 mois)', $releve],
            ['Taux de remplacement', $tauxRemplacement !== null ? $tauxRemplacement.' %' : '-'],
        ] as [$libelle, $valeur])
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">{{ $libelle }}</div>
                <div class="mt-1 text-3xl font-bold">{{ $valeur }}</div>
            </div>
        @endforeach
    </div>

    <p class="mb-6 text-sm text-gray-600">
        Taux de remplacement = relève validée sur 12 mois ({{ $releve }}) ÷ sorties du cheptel sur 12 mois ({{ $sorties }}).
        @if ($partAges !== null) Reproducteurs âgés : {{ $partAges }} % du cheptel actif ({{ $effectif }} animaux). @endif
    </p>

    {{-- Critères --}}
    <form method="POST" action="{{ route('proprietaire.selection.criteres') }}" class="mb-6 rounded-lg bg-white p-5 shadow">
        @csrf @method('PUT')
        <h2 class="mb-3 font-semibold">Critères de sélection des futurs reproducteurs</h2>
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <x-input-label for="poids_min_kg" value="Poids minimum (kg)" />
                <x-text-input id="poids_min_kg" name="poids_min_kg" type="number" step="0.01" class="mt-1 block w-full" :value="old('poids_min_kg', $criteres->poids_min_kg)" required />
                <x-input-error :messages="$errors->get('poids_min_kg')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="age_min_jours" value="Âge minimum du lot (jours)" />
                <x-text-input id="age_min_jours" name="age_min_jours" type="number" class="mt-1 block w-full" :value="old('age_min_jours', $criteres->age_min_jours)" required />
                <x-input-error :messages="$errors->get('age_min_jours')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="prolificite_mere_min" value="Prolificité minimale de la mère (nés vivants)" />
                <x-text-input id="prolificite_mere_min" name="prolificite_mere_min" type="number" step="0.1" class="mt-1 block w-full" :value="old('prolificite_mere_min', $criteres->prolificite_mere_min)" />
                <x-input-error :messages="$errors->get('prolificite_mere_min')" class="mt-1" />
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Laissez la prolificité vide pour ne pas contrôler le pedigree de la mère.</p>
        <x-primary-button class="mt-3">Enregistrer les critères</x-primary-button>
    </form>

    {{-- Validations en attente --}}
    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Nouveaux reproducteurs en attente de validation</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Matricule</th>
                    <th class="px-4 py-2">Sexe</th>
                    <th class="px-4 py-2">Lot d'origine</th>
                    <th class="px-4 py-2">Mère</th>
                    <th class="px-4 py-2">Père</th>
                    <th class="px-4 py-2">Poids</th>
                    <th class="px-4 py-2">Cage</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($enAttente as $animal)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $animal->matricule }}</td>
                        <td class="px-4 py-2">{{ $animal->sexe->libelle() }}</td>
                        <td class="px-4 py-2">{{ $animal->lotOrigine?->code ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $animal->mere?->matricule ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $animal->pere?->matricule ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $animal->pesees->sortByDesc('date_pesee')->first()?->poids_kg ?? '-' }} kg</td>
                        <td class="px-4 py-2">{{ $animal->cage?->code ?? '-' }}</td>
                        <td class="flex gap-2 px-4 py-2">
                            <form method="POST" action="{{ route('proprietaire.selection.valider', $animal) }}">
                                @csrf
                                <button class="rounded bg-emerald-600 px-3 py-1 text-white">Valider</button>
                            </form>
                            <form method="POST" action="{{ route('proprietaire.selection.refuser', $animal) }}"
                                  onsubmit="return confirm('Refuser cet animal ? Il sera réformé et sa cage libérée.')">
                                @csrf
                                <button class="rounded border border-red-300 px-3 py-1 text-red-600">Refuser</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Aucun animal en attente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Renouvellement --}}
    <div class="mb-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Tableau de renouvellement : reproducteurs de plus de {{ $ageReforme }} mois</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Matricule</th>
                    <th class="px-4 py-2">Sexe</th>
                    <th class="px-4 py-2">Âge</th>
                    <th class="px-4 py-2">Cage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ages as $animal)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $animal->matricule }}</td>
                        <td class="px-4 py-2">{{ $animal->sexe->libelle() }}</td>
                        <td class="px-4 py-2">{{ $animal->ageEnMois() }} mois</td>
                        <td class="px-4 py-2">{{ $animal->cage?->code ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucun reproducteur à renouveler.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Lots en cours --}}
    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Lots d'engraissement en cours</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Lot</th>
                    <th class="px-4 py-2">Cage</th>
                    <th class="px-4 py-2">Effectif</th>
                    <th class="px-4 py-2">Âge</th>
                    <th class="px-4 py-2">Dernier poids moyen</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lots as $lot)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $lot->code }}</td>
                        <td class="px-4 py-2">{{ $lot->cage?->code ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $lot->effectif_actuel }} / {{ $lot->effectif_initial }}</td>
                        <td class="px-4 py-2">{{ $lot->ageEnJours() ?? '?' }} j</td>
                        <td class="px-4 py-2">
                            {{ $lot->dernierePesee ? number_format($lot->dernierePesee->poids_moyen_kg, 2, ',', ' ').' kg ('.$lot->dernierePesee->date_pesee->format('d/m').')' : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Aucun lot en cours.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>