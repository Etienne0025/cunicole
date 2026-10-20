<x-layouts.gestionnaire title="Reproducteurs">
    <a href="{{ route('gestionnaire.reproducteurs.create') }}" class="mb-3 block rounded bg-emerald-600 py-3 text-center text-white">
        + Nouveau reproducteur
    </a>

    <form method="GET" class="mb-3 grid grid-cols-2 gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Matricule ou nom"
               class="col-span-2 rounded border-gray-300 text-sm">
        <select name="sexe" onchange="this.form.submit()" class="rounded border-gray-300 text-sm">
            <option value="">Mâles et femelles</option>
            @foreach ($sexes as $sexe)
                <option value="{{ $sexe->value }}" @selected(request('sexe') === $sexe->value)>{{ $sexe->libelle() }}</option>
            @endforeach
        </select>
        <select name="statut" onchange="this.form.submit()" class="rounded border-gray-300 text-sm">
            <option value="tous" @selected($statutChoisi === 'tous')>Tous les statuts</option>
            @foreach ($statuts as $statut)
                <option value="{{ $statut->value }}" @selected($statutChoisi === $statut->value)>{{ $statut->libelle() }}</option>
            @endforeach
        </select>
    </form>

    <div class="space-y-2">
        @forelse ($reproducteurs as $reproducteur)
            <a href="{{ route('gestionnaire.reproducteurs.show', $reproducteur) }}"
               class="flex items-center justify-between rounded-lg bg-white p-3 shadow">
                <div>
                    <div class="font-semibold">{{ $reproducteur->matricule }}
                        @if ($reproducteur->nom)<span class="font-normal text-gray-500">· {{ $reproducteur->nom }}</span>@endif
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $reproducteur->sexe->libelle() }} · {{ $reproducteur->ageEnMois() }} mois
                    </div>
                </div>
                <div class="text-right text-xs">
                    @if ($reproducteur->estActif())
                        {!! $reproducteur->cage
                            ? '<span class="rounded-full bg-blue-100 px-2 py-0.5 text-blue-800">'.e($reproducteur->cage->code).'</span>'
                            : '<span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Sans cage</span>' !!}
                    @else
                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-gray-700">{{ $reproducteur->statut->libelle() }}</span>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucun reproducteur trouvé.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $reproducteurs->links() }}</div>
</x-layouts.gestionnaire>