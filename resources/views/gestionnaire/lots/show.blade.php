@use('App\Enums\StatutLot')

@php $c = $situation['criteres']; @endphp

<x-layouts.gestionnaire :title="$lot->code">
    <div class="space-y-4">

        <div class="rounded-lg bg-white p-4 shadow">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xl font-bold">{{ $lot->code }}</div>
                    <div class="text-sm text-gray-500">
                        Cage
                        @if ($lot->cage)
                            <a class="text-blue-600 underline" href="{{ route('gestionnaire.cages.show', $lot->cage) }}">{{ $lot->cage->code }}</a>
                        @else - @endif
                    </div>
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $lot->statut === StatutLot::EnCours ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">
                    {{ $lot->statut->libelle() }}
                </span>
            </div>

            <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                <div><dt class="text-gray-500">Effectif</dt><dd class="text-xl font-bold">{{ $lot->effectif_actuel }}<span class="text-sm font-normal text-gray-500"> / {{ $lot->effectif_initial }}</span></dd></div>
                <div><dt class="text-gray-500">Âge</dt><dd class="text-xl font-bold">{{ $lot->ageEnJours() ?? '?' }} j</dd></div>
                <div><dt class="text-gray-500">Début</dt><dd class="text-xl font-bold">{{ $lot->date_debut->format('d/m') }}</dd></div>
            </dl>

            <p class="mt-3 text-sm text-gray-600">
                Mère : <strong>{{ $situation['mere']?->matricule ?? 'inconnue' }}</strong>
                · Père : <strong>{{ $situation['pere']?->matricule ?? 'inconnu' }}</strong>
            </p>
        </div>

        {{-- Critères de sélection --}}
        <div class="rounded-lg bg-white p-4 shadow">
            <h2 class="mb-2 font-semibold">Critères de sélection (fixés par le propriétaire)</h2>
            <ul class="space-y-1 text-sm">
                <li>{{ $situation['age_ok'] ? '✔' : '✘' }} Âge du lot : {{ $situation['age'] ?? '?' }} j (minimum {{ $c->age_min_jours }} j)</li>
                <li>⚖ Poids minimum du sujet : {{ number_format($c->poids_min_kg, 2, ',', ' ') }} kg (vérifié à la conversion)</li>
                @if ($c->prolificite_mere_min !== null)
                    <li>{{ $situation['mere_ok'] ? '✔' : '✘' }} Prolificité de la mère : {{ $situation['moyenne_mere'] ?? '?' }} nés vivants en moyenne (minimum {{ $c->prolificite_mere_min }})</li>
                @endif
            </ul>
        </div>

        @if ($lot->statut === StatutLot::EnCours)
            {{-- Pesée du lot --}}
            <div class="rounded-lg bg-white p-4 shadow">
                <h2 class="mb-2 font-semibold">Pesée du lot</h2>

                <form method="POST" action="{{ route('gestionnaire.lots.pesees.store', $lot) }}" class="mb-3 grid grid-cols-2 gap-2">
                    @csrf
                    <input type="date" name="date_pesee" value="{{ old('date_pesee', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="rounded border-gray-300 text-sm" required>
                    <input type="number" name="nombre_pese" min="1" max="{{ $lot->effectif_actuel }}" value="{{ old('nombre_pese', min(5, $lot->effectif_actuel)) }}" placeholder="Lapins pesés" class="rounded border-gray-300 text-sm" required>
                    <input type="number" step="0.01" min="0.05" max="8" name="poids_moyen_kg" value="{{ old('poids_moyen_kg') }}" placeholder="Poids moyen (kg)" class="rounded border-gray-300 text-sm" required>
                    <button class="rounded bg-slate-800 text-sm text-white">Ajouter</button>
                    <div class="col-span-2">
                        <x-input-error :messages="$errors->get('poids_moyen_kg')" />
                        <x-input-error :messages="$errors->get('nombre_pese')" />
                        <x-input-error :messages="$errors->get('date_pesee')" />
                    </div>
                </form>

                @forelse ($pesees as $pesee)
                    <div class="flex justify-between border-b py-1 text-sm last:border-0">
                        <span>{{ $pesee->date_pesee->format('d/m/Y') }} <span class="text-xs text-gray-500">({{ $pesee->nombre_pese }} pesés)</span></span>
                        <span class="font-medium">{{ number_format($pesee->poids_moyen_kg, 2, ',', ' ') }} kg</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Aucune pesée enregistrée.</p>
                @endforelse
            </div>

            {{-- Conversion --}}
            <form method="POST" action="{{ route('gestionnaire.lots.convertir', $lot) }}"
                  x-data="{ sexe: '{{ old('sexe', 'femelle') }}' }"
                  class="space-y-3 rounded-lg bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold">Convertir un lapin en reproducteur</h2>
                <p class="text-xs text-gray-500">Tatouez d'abord l'oreille du lapin choisi, pesez-le, puis saisissez son matricule.</p>

                <div>
                    <x-input-label for="matricule" value="Matricule (code de tatouage)" />
                    <x-text-input id="matricule" name="matricule" class="mt-1 block w-full uppercase" :value="old('matricule')" maxlength="30" required />
                    <x-input-error :messages="$errors->get('matricule')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="nom" value="Nom (facultatif)" />
                    <x-text-input id="nom" name="nom" class="mt-1 block w-full" :value="old('nom')" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <x-input-label for="sexe" value="Sexe" />
                        <select id="sexe" name="sexe" x-model="sexe" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                            <option value="femelle">Femelle</option>
                            <option value="male">Mâle</option>
                        </select>
                        <x-input-error :messages="$errors->get('sexe')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="poids_kg" value="Poids actuel (kg)" />
                        <input id="poids_kg" type="number" step="0.01" min="0.5" max="15" name="poids_kg" value="{{ old('poids_kg') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <x-input-error :messages="$errors->get('poids_kg')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Cage individuelle" />
                    <select name="cage_id" x-show="sexe === 'femelle'" :disabled="sexe !== 'femelle'"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choisir...</option>
                        @foreach ($cagesFemelles as $cage)
                            <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>{{ $cage->code }} · {{ $cage->batiment->nom }} ({{ $cage->type->libelle() }})</option>
                        @endforeach
                    </select>
                    <select name="cage_id" x-show="sexe === 'male'" x-cloak :disabled="sexe !== 'male'"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choisir...</option>
                        @foreach ($cagesMales as $cage)
                            <option value="{{ $cage->id }}" @selected(old('cage_id') == $cage->id)>{{ $cage->code }} · {{ $cage->batiment->nom }} ({{ $cage->type->libelle() }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('cage_id')" class="mt-1" />
                </div>

                <button class="w-full rounded bg-emerald-600 py-3 text-white">Convertir en reproducteur</button>
            </form>
        @endif

        @include('gestionnaire.sante._bloc', [
    'cible' => $lot,
    'type' => 'lot',
    'actif' => $lot->statut === StatutLot::EnCours,
    'max' => $lot->effectif_actuel,
])

        @if ($issus->isNotEmpty())
            <div class="rounded-lg bg-white p-4 shadow">
                <h2 class="mb-2 font-semibold">Reproducteurs issus de ce lot</h2>
                @foreach ($issus as $issu)
                    <a href="{{ route('gestionnaire.reproducteurs.show', $issu) }}" class="flex justify-between border-b py-2 text-sm last:border-0">
                        <span>{{ $issu->matricule }} · {{ $issu->sexe->libelle() }}</span>
                        <span class="text-gray-500">{{ $issu->estValide() ? $issu->statut->libelle() : 'En attente de validation' }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.gestionnaire>