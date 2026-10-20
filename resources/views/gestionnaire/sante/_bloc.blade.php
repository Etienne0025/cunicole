@php
    $delais = $produitsSoins->pluck('delai_attente_jours', 'id');
    $erreursTraitement = ['produit_id', 'nom_traitement', 'symptomes', 'date_debut', 'duree_jours', 'delai_attente_jours', 'quantite'];
@endphp

<div class="space-y-4">

    @if ($bloquant)
        <div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <strong>Bloqué pour la vente</strong> jusqu'au {{ $bloquant->date_fin_attente->format('d/m/Y') }}
            (délai d'attente : {{ $bloquant->nom_traitement }}).
        </div>
    @endif

    <div class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Santé</h2>

        <h3 class="text-sm font-medium text-gray-600">Vaccinations</h3>
        @forelse ($vaccinations as $vaccination)
            <div class="flex justify-between border-b py-1 text-sm last:border-0">
                <span>{{ $vaccination->nom_vaccin }}@if ($vaccination->anterieure) <span class="text-xs text-gray-500">(antérieure)</span>@endif</span>
                <span class="text-gray-500">{{ $vaccination->date_vaccination->format('d/m/Y') }}</span>
            </div>
        @empty
            <p class="mb-2 text-sm text-gray-500">Aucune vaccination enregistrée.</p>
        @endforelse

        <h3 class="mt-3 text-sm font-medium text-gray-600">Traitements</h3>
        @forelse ($traitements as $traitement)
            <div class="border-b py-1 text-sm last:border-0">
                <div class="flex justify-between">
                    <span>{{ $traitement->nom_traitement }}</span>
                    <span class="text-gray-500">{{ $traitement->date_debut->format('d/m/Y') }}</span>
                </div>
                <div class="text-xs text-gray-500">
                    {{ $traitement->symptomes }} · {{ $traitement->duree_jours }} j
                    · attente jusqu'au {{ $traitement->date_fin_attente->format('d/m/Y') }}
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun traitement enregistré.</p>
        @endforelse
    </div>

    @if ($actif)
        {{-- Traitement curatif --}}
        <div x-data="{ ouvert: {{ $errors->hasAny($erreursTraitement) ? 'true' : 'false' }}, delai: {{ (int) old('delai_attente_jours', 0) }}, delais: @js($delais) }"
             class="rounded-lg bg-white p-4 shadow">
            <button type="button" @click="ouvert = !ouvert" class="w-full font-semibold text-amber-700">Soigner (traitement curatif)</button>

            <form x-show="ouvert" x-cloak method="POST" action="{{ route('gestionnaire.sante.traiter') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="cible_type" value="{{ $type }}">
                <input type="hidden" name="cible_id" value="{{ $cible->id }}">

                <div>
                    <x-input-label for="symptomes-{{ $type }}" value="Symptômes observés" />
                    <textarea id="symptomes-{{ $type }}" name="symptomes" rows="2" maxlength="500" required
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('symptomes') }}</textarea>
                    <x-input-error :messages="$errors->get('symptomes')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="produit-{{ $type }}" value="Produit de la pharmacie (facultatif)" />
                    <select id="produit-{{ $type }}" name="produit_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                            @change="if (delais[$event.target.value] !== undefined) delai = delais[$event.target.value]">
                        <option value="">Autre traitement (hors pharmacie)</option>
                        @foreach ($produitsSoins as $produit)
                            <option value="{{ $produit->id }}" @selected(old('produit_id') == $produit->id)>
                                {{ $produit->nom }} (stock : {{ number_format($produit->stock_actuel, 2, ',', ' ') }} {{ $produit->unite }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('produit_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="nom-traitement-{{ $type }}" value="Nom du traitement (si hors pharmacie)" />
                    <x-text-input id="nom-traitement-{{ $type }}" name="nom_traitement" class="mt-1 block w-full" :value="old('nom_traitement')" maxlength="100" />
                    <x-input-error :messages="$errors->get('nom_traitement')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="quantite-{{ $type }}" value="Quantité utilisée (produit de la pharmacie)" />
                    <input id="quantite-{{ $type }}" type="number" step="0.01" min="0.01" name="quantite" value="{{ old('quantite') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error :messages="$errors->get('quantite')" class="mt-1" />
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <x-input-label for="debut-{{ $type }}" value="Début" />
                        <input id="debut-{{ $type }}" type="date" name="date_debut" value="{{ old('date_debut', today()->toDateString()) }}"
                               max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                    </div>
                    <div>
                        <x-input-label for="duree-{{ $type }}" value="Durée (jours)" />
                        <input id="duree-{{ $type }}" type="number" min="1" max="60" name="duree_jours" value="{{ old('duree_jours', 1) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                    </div>
                    <div>
                        <x-input-label for="delai-{{ $type }}" value="Délai d'attente (j)" />
                        <input id="delai-{{ $type }}" type="number" min="0" max="365" name="delai_attente_jours" x-model.number="delai"
                               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                    </div>
                </div>
                <x-input-error :messages="$errors->get('date_debut')" />
                <x-input-error :messages="$errors->get('duree_jours')" />
                <x-input-error :messages="$errors->get('delai_attente_jours')" />

                <p class="text-xs text-gray-500">
                    Le délai d'attente se prérempli avec celui du produit : reprenez celui de l'ordonnance de votre vétérinaire.
                    Pendant ce délai, la vente est bloquée.
                </p>

                <button class="w-full rounded bg-amber-600 py-3 text-white">Enregistrer le traitement</button>
            </form>
        </div>

        {{-- Vaccination déjà effectuée --}}
        @if ($protocolesActifs->isNotEmpty())
            <div x-data="{ ouvert: {{ $errors->hasAny(['protocole_id', 'date_vaccination']) ? 'true' : 'false' }} }" class="rounded-lg bg-white p-4 shadow">
                <button type="button" @click="ouvert = !ouvert" class="w-full font-semibold text-blue-700">Vaccination déjà effectuée</button>

                <form x-show="ouvert" x-cloak method="POST" action="{{ route('gestionnaire.sante.vaccination_anterieure') }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="cible_type" value="{{ $type }}">
                    <input type="hidden" name="cible_id" value="{{ $cible->id }}">

                    <select name="protocole_id" class="block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">Protocole concerné...</option>
                        @foreach ($protocolesActifs as $protocole)
                            <option value="{{ $protocole->id }}" @selected(old('protocole_id') == $protocole->id)>{{ $protocole->nom }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('protocole_id')" />

                    <input type="date" name="date_vaccination" value="{{ old('date_vaccination') }}" max="{{ today()->toDateString() }}"
                           class="block w-full rounded-md border-gray-300 shadow-sm" required>
                    <x-input-error :messages="$errors->get('date_vaccination')" />

                    <p class="text-xs text-gray-500">À utiliser pour une vaccination faite avant l'application. Le stock n'est pas décompté.</p>
                    <button class="w-full rounded bg-blue-600 py-3 text-white">Enregistrer</button>
                </form>
            </div>
        @endif

        @include('gestionnaire.sante._deces', [
            'type' => $type,
            'cible' => $cible,
            'avecNombre' => $type === 'lot',
            'max' => $max ?? 1,
        ])
    @endif
</div>