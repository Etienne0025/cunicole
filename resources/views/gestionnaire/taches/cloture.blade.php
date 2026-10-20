@use('App\Enums\StatutTache')

@php
    $ouvertes = $taches->filter(fn ($t) => $t->statut === StatutTache::AFaire);
    $nonFaites = $taches->filter(fn ($t) => $t->statut === StatutTache::NonFaite);
@endphp

<x-layouts.gestionnaire title="Clôture de journée">
    <div class="mb-4 rounded-lg bg-white p-4 shadow">
        <div class="text-lg font-bold">{{ $jour->translatedFormat('l d F Y') }}</div>
        <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
            <div><dt class="text-gray-500">Tâches</dt><dd class="text-xl font-bold">{{ $progression['total'] }}</dd></div>
            <div><dt class="text-gray-500">Faites</dt><dd class="text-xl font-bold text-emerald-700">{{ $progression['faites'] }}</dd></div>
            <div><dt class="text-gray-500">Restantes</dt><dd class="text-xl font-bold text-red-600">{{ $progression['restantes'] + $progression['non_faites'] }}</dd></div>
        </dl>
    </div>

    @if ($cloture)
        <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">
            Journée clôturée le {{ $cloture->cloturee_le->format('d/m/Y à H:i') }}
            par {{ $cloture->utilisateur?->nom_complet ?? '-' }}.
            @if ($cloture->commentaire)
                <p class="mt-2">{{ $cloture->commentaire }}</p>
            @endif
        </div>

        @foreach ($nonFaites as $tache)
            <div class="mb-2 rounded-lg bg-white p-3 text-sm shadow">
                <div class="font-medium">{{ $tache->titre }}</div>
                <div class="text-red-700">{{ $tache->justification }}</div>
            </div>
        @endforeach
    @else
        <form method="POST" action="{{ route('gestionnaire.taches.cloturer') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $jour->toDateString() }}">
            <x-input-error :messages="$errors->get('date')" />

            @forelse ($ouvertes as $tache)
                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="font-medium">{{ $tache->titre }}</div>
                    @if ($tache->description)
                        <div class="text-xs text-gray-500">{{ $tache->description }}</div>
                    @endif
                    <textarea name="justifications[{{ $tache->id }}]" rows="2" required minlength="5" maxlength="500"
                              placeholder="Pourquoi cette tâche n'a-t-elle pas été faite ?"
                              class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('justifications.'.$tache->id) }}</textarea>
                    <x-input-error :messages="$errors->get('justifications.'.$tache->id)" class="mt-1" />
                </div>
            @empty
                <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-900">Toutes les tâches de cette journée sont traitées.</p>
            @endforelse

            <div>
                <x-input-label for="commentaire" value="Commentaire de fin de journée (facultatif)" />
                <textarea id="commentaire" name="commentaire" rows="3" maxlength="1000"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('commentaire') }}</textarea>
            </div>

            <button class="w-full rounded bg-slate-800 py-3 text-white"
                    onclick="return confirm('Clôturer cette journée ? Cette action est définitive.')">
                Clôturer la journée
            </button>
        </form>
    @endif

    <a href="{{ route('gestionnaire.taches.index') }}" class="mt-4 block text-center text-sm text-blue-600 underline">← Retour aux tâches</a>
</x-layouts.gestionnaire>