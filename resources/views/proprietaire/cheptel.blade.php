<x-layouts.proprietaire title="Cheptel reproducteur">
    <div class="mb-6 grid gap-4 md:grid-cols-4">
        @foreach ([
            ['Femelles reproductrices', $femelles],
            ['Mâles reproducteurs', $males],
            ['Total actif', $femelles + $males],
            ['Sans cage', $sansCage],
        ] as [$libelle, $valeur])
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">{{ $libelle }}</div>
                <div class="mt-1 text-3xl font-bold">{{ $valeur }}</div>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Matricule</th>
                    <th class="px-4 py-2">Nom</th>
                    <th class="px-4 py-2">Sexe</th>
                    <th class="px-4 py-2">Âge</th>
                    <th class="px-4 py-2">Race</th>
                    <th class="px-4 py-2">Cage</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reproducteurs as $reproducteur)
                    <tr class="border-t">
                        <td class="px-4 py-2 font-medium">{{ $reproducteur->matricule }}</td>
                        <td class="px-4 py-2">{{ $reproducteur->nom ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $reproducteur->sexe->libelle() }}</td>
                        <td class="px-4 py-2">{{ $reproducteur->ageEnMois() }} mois</td>
                        <td class="px-4 py-2">{{ $reproducteur->race ?? '-' }}</td>
                        <td class="px-4 py-2">{{ $reproducteur->cage?->code ?? 'Sans cage' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun reproducteur enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $reproducteurs->links() }}</div>
</x-layouts.proprietaire>