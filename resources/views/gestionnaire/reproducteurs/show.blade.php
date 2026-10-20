<x-layouts.gestionnaire :title="$reproducteur->matricule">
    <div class="space-y-4">

        {{-- Identité --}}
        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xl font-bold">{{ $reproducteur->matricule }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $reproducteur->nom ?? 'Sans nom' }} · {{ $reproducteur->sexe->libelle() }} · {{ $reproducteur->ageEnMois() }} mois
                    </div>
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $reproducteur->estActif() ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">
                    {{ $reproducteur->statut->libelle() }}
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div>
                    <dt class="text-gray-500">Cage</dt>
                    <dd>
                        @if ($reproducteur->cage)
                            <a class="text-blue-600 underline" href="{{ route('gestionnaire.cages.show', $reproducteur->cage) }}">
                                {{ $reproducteur->cage->code }}
                            </a>
                        @else
                            Aucune
                        @endif
                    </dd>
                </div>
                <div><dt class="text-gray-500">Race</dt><dd>{{ $reproducteur->race ?? '-' }}</dd></div>
                <div><dt class="text-gray-500">Naissance</dt><dd>{{ $reproducteur->date_naissance->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Origine</dt><dd>{{ $reproducteur->origine->libelle() }}</dd></div>
                <div>
                    <dt class="text-gray-500">Mère</dt>
                    <dd>
                        @if ($reproducteur->mere)
                            <a class="text-blue-600 underline" href="{{ route('gestionnaire.reproducteurs.show', $reproducteur->mere) }}">{{ $reproducteur->mere->matricule }}</a>
                        @else - @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Père</dt>
                    <dd>
                        @if ($reproducteur->pere)
                            <a class="text-blue-600 underline" href="{{ route('gestionnaire.reproducteurs.show', $reproducteur->pere) }}">{{ $reproducteur->pere->matricule }}</a>
                        @else - @endif
                    </dd>
                </div>
            </dl>

            @unless ($reproducteur->estActif())
                <p class="mt-3 rounded bg-gray-100 p-2 text-sm">
                    Sorti le {{ $reproducteur->date_sortie?->format('d/m/Y') }}
                    @if ($reproducteur->motif_sortie) : {{ $reproducteur->motif_sortie }} @endif
                </p>
            @endunless

            @if ($reproducteur->estActif() && ! $reproducteur->estValide())
    <p class="mt-3 rounded bg-amber-100 p-2 text-sm text-amber-800">
        En attente de validation par le propriétaire. Il ne peut pas encore être saillie.
    </p>
@endif
@if ($reproducteur->lotOrigine)
    <p class="mt-3 text-sm text-gray-600">Issu du lot {{ $reproducteur->lotOrigine->code }}.</p>
@endif

            @if ($reproducteur->notes)
                <p class="mt-3 text-sm">{{ $reproducteur->notes }}</p>
            @endif
        </div>

        @if ($reproducteur->estActif())
            {{-- Placement / déplacement --}}
            <form method="POST" action="{{ route('gestionnaire.reproducteurs.deplacer', $reproducteur) }}"
                  class="space-y-3 rounded-lg bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">{{ $reproducteur->cage ? 'Déplacer vers une autre cage' : 'Placer dans une cage' }}</h2>

                @if ($cagesDisponibles->isEmpty())
                    <p class="text-sm text-amber-700">
                        Aucune cage disponible pour ce reproducteur (cage activée, propre, du bon type et non pleine).
                    </p>
                @else
                    <select name="cage_id" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">Choisir une cage...</option>
                        @foreach ($cagesDisponibles as $cage)
                            <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>
                                {{ $cage->code }} · {{ $cage->batiment->nom }} ({{ $cage->type->libelle() }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cage_id')" />
                    <input name="motif" value="{{ old('motif') }}" placeholder="Motif (facultatif)"
                           class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <x-primary-button>Valider</x-primary-button>
                @endif
            </form>

            {{-- Pesée --}}
            <div class="rounded-lg bg-white p-4 shadow">
                <h2 class="mb-2 font-semibold">Pesées</h2>

                <form method="POST" action="{{ route('gestionnaire.reproducteurs.pesees.store', $reproducteur) }}" class="mb-3 grid grid-cols-3 gap-2">
                    @csrf
                    <input type="date" name="date_pesee" value="{{ old('date_pesee', now()->toDateString()) }}" class="rounded border-gray-300 text-sm" required>
                    <input type="number" step="0.01" min="0.1" max="15" name="poids_kg" value="{{ old('poids_kg') }}" placeholder="Poids (kg)" class="rounded border-gray-300 text-sm" required>
                    <button class="rounded bg-slate-800 text-sm text-white">Ajouter</button>
                    <div class="col-span-3">
                        <x-input-error :messages="$errors->get('poids_kg')" />
                        <x-input-error :messages="$errors->get('date_pesee')" />
                    </div>
                </form>

                @forelse ($pesees as $pesee)
                    <div class="flex justify-between border-b py-1 text-sm last:border-0">
                        <span>{{ $pesee->date_pesee->format('d/m/Y') }}</span>
                        <span class="font-medium">{{ number_format($pesee->poids_kg, 2, ',', ' ') }} kg</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Aucune pesée enregistrée.</p>
                @endforelse
            </div>
        @endif

        @include('gestionnaire.sante._bloc', [
    'cible' => $reproducteur,
    'type' => 'reproducteur',
    'actif' => $reproducteur->estActif(),
    'max' => 1,
])

        {{-- Historique des cages --}}
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="mb-2 font-semibold">Historique des cages</h2>
            @forelse ($mouvements as $mouvement)
                <div class="border-b py-2 text-sm last:border-0">
                    <div>
                        {{ $mouvement->code_origine ?? 'Aucune' }} → <strong>{{ $mouvement->code_destination ?? 'Aucune' }}</strong>
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $mouvement->effectue_le->format('d/m/Y H:i') }}
                        @if ($mouvement->utilisateur) · {{ $mouvement->utilisateur->nom_complet }} @endif
                        @if ($mouvement->motif) · {{ $mouvement->motif }} @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Aucun mouvement.</p>
            @endforelse
        </div>

        {{-- Actions --}}
        <div class="space-y-2">
            <a href="{{ route('gestionnaire.reproducteurs.edit', $reproducteur) }}" class="block rounded border bg-white py-3 text-center">Modifier la fiche</a>

            @if ($reproducteur->estActif())
                <div x-data="{ ouvert: false }" class="rounded-lg border border-red-200 bg-white p-4">
                    <button type="button" @click="ouvert = !ouvert" class="w-full text-red-600">Sortie du cheptel (réforme, vente)</button>

                    <form x-show="ouvert" x-cloak method="POST" action="{{ route('gestionnaire.reproducteurs.sortie', $reproducteur) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="statut" value="reforme">
<p class="text-xs text-gray-500">
    L'animal est réformé et sa cage libérée. Pour le vendre (même réformé), utilisez Plus → Ventes.
</p>
                        <input type="date" name="date_sortie" value="{{ now()->toDateString() }}" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                        <input name="motif_sortie" placeholder="Motif (facultatif)" class="block w-full rounded-md border-gray-300 shadow-sm">
                        <x-input-error :messages="$errors->get('date_sortie')" />
                        <button class="w-full rounded bg-red-600 py-2 text-white" onclick="return confirm('Confirmer la sortie de cet animal ?')">Confirmer la sortie</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-layouts.gestionnaire>