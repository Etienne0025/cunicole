<x-layouts.gestionnaire title="Alimentation">

    <section class="mb-6">
        <h2 class="mb-2 font-semibold">Distribution du jour</h2>

        <div class="space-y-3">
            @forelse ($lignes as $ligne)
                @php
                    $ration = $ligne['ration'];
                    $distribution = $ligne['distribution'];
                    $ecart = $distribution?->ecartPourcent();
                    $erreurIci = (int) old('ration_id') === $ration->id;
                @endphp

                <div class="rounded-lg bg-white p-4 shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-medium">{{ $ration->aliment->nom }}</div>
                            <div class="text-sm text-gray-600">{{ $ration->categorie->libelle() }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $ligne['nombre'] }} animal(aux) · {{ $ration->grammes_par_jour }} g {{ $ration->categorie->parUnite() }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Quantité théorique</div>
                            <div class="text-lg font-bold">{{ number_format($ligne['theorique'], 2, ',', ' ') }} kg</div>
                        </div>
                    </div>

                    @if ($distribution)
                        <p class="mt-3 rounded bg-emerald-50 p-2 text-sm text-emerald-900">
                            ✔ Servi à {{ $distribution->created_at->format('H:i') }} :
                            <strong>{{ number_format($distribution->quantite_servie_kg, 2, ',', ' ') }} kg</strong>
                            @if ($ecart !== null)
                                ({{ $ecart > 0 ? '+' : '' }}{{ number_format($ecart, 1, ',', ' ') }} % du théorique)
                            @endif
                        </p>
                    @else
                        <form method="POST" action="{{ route('gestionnaire.alimentation.distribuer', $ration) }}" class="mt-3 flex gap-2">
                            @csrf
                            <input type="hidden" name="ration_id" value="{{ $ration->id }}">
                            <input type="number" step="0.01" min="0.01" max="1000" name="quantite_servie_kg" required
                                   value="{{ $erreurIci ? old('quantite_servie_kg') : number_format($ligne['theorique'], 2, '.', '') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm">
                            <button class="shrink-0 rounded bg-emerald-600 px-4 text-white">Distribué</button>
                        </form>
                        @if ($erreurIci)
                            <x-input-error :messages="$errors->get('quantite_servie_kg')" class="mt-1" />
                        @endif
                        <p class="mt-1 text-xs text-gray-500">Pesez ce que vous servez et indiquez la quantité réelle, en kg.</p>
                    @endif
                </div>
            @empty
                <p class="rounded-lg bg-white p-4 text-sm text-gray-500 shadow">
                    Aucune distribution à faire aujourd'hui. Le propriétaire doit définir les rations dans l'application.
                </p>
            @endforelse
        </div>
    </section>

    <section class="mb-6 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Stocks</h2>
        @forelse ($etats as $etat)
            <div class="flex justify-between border-b py-2 text-sm last:border-0">
                <span>{{ $etat['aliment']->nom }}</span>
                <span class="{{ $etat['bas'] ? 'font-semibold text-red-600' : '' }}">
                    {{ number_format($etat['aliment']->stock_kg, 2, ',', ' ') }} kg
                    @if ($etat['autonomie'] !== null) · {{ number_format($etat['autonomie'], 1, ',', ' ') }} j @endif
                    @if ($etat['bas']) · stock faible @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun aliment. Le propriétaire doit les créer.</p>
        @endforelse
    </section>

    @if ($aliments->isNotEmpty())
        <form method="POST" action="{{ route('gestionnaire.alimentation.recevoir') }}"
              x-data="{ poidsParAliment: @js($poidsSacs), poids: @js(old('poids_sac_kg')), sacs: @js(old('nombre_sacs')) }"
              class="mb-6 space-y-3 rounded-lg bg-white p-4 shadow">
            @csrf
            <h2 class="font-semibold">Réception de sacs</h2>

            <div>
                <x-input-label for="aliment_id" value="Aliment reçu" />
                <select id="aliment_id" name="aliment_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                        @change="if (poidsParAliment[$event.target.value]) poids = poidsParAliment[$event.target.value]">
                    <option value="">Choisir...</option>
                    @foreach ($aliments as $aliment)
                        <option value="{{ $aliment->id }}" @selected(old('aliment_id') == $aliment->id)>{{ $aliment->nom }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('aliment_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="nombre_sacs" value="Nombre de sacs" />
                    <input id="nombre_sacs" type="number" min="1" max="1000" name="nombre_sacs" x-model.number="sacs" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error :messages="$errors->get('nombre_sacs')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="poids_sac_kg" value="Poids d'un sac (kg)" />
                    <input id="poids_sac_kg" type="number" step="0.01" min="0.1" max="5000" name="poids_sac_kg" x-model="poids" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error :messages="$errors->get('poids_sac_kg')" class="mt-1" />
                </div>
            </div>

            <p class="text-sm" x-show="sacs && poids" x-cloak>
                Total reçu : <strong x-text="(sacs * poids).toFixed(2)"></strong> kg
            </p>
            <p class="text-xs text-gray-500">Pour de l'aliment en vrac, saisissez 1 sac et indiquez le poids total reçu.</p>

            <div>
                <x-input-label for="date_reception" value="Date de réception" />
                <input id="date_reception" type="date" name="date_reception" value="{{ old('date_reception', today()->toDateString()) }}"
                       max="{{ today()->toDateString() }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <x-input-error :messages="$errors->get('date_reception')" class="mt-1" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <input name="fournisseur" value="{{ old('fournisseur') }}" maxlength="100" placeholder="Fournisseur (facultatif)"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <input type="number" step="0.01" min="0" name="cout_total" value="{{ old('cout_total') }}" placeholder="Montant payé (facultatif)"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
            </div>
            <x-input-error :messages="$errors->get('cout_total')" />

            <button class="w-full rounded bg-slate-800 py-3 text-white">Valider la mise en stock</button>
        </form>
    @endif

    <section class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Dernières réceptions</h2>
        @forelse ($receptions as $reception)
            <div class="border-b py-2 text-sm last:border-0">
                <div class="flex justify-between">
                    <span class="font-medium">{{ $reception->aliment->nom }}</span>
                    <span class="text-emerald-700">+{{ number_format($reception->quantite_kg, 2, ',', ' ') }} kg</span>
                </div>
                <div class="text-xs text-gray-500">
                    {{ $reception->date_mouvement->format('d/m/Y') }}
                    @if ($reception->nombre_sacs) · {{ $reception->nombre_sacs }} sac(s) @endif
                    @if ($reception->fournisseur) · {{ $reception->fournisseur }} @endif
                    @if ($reception->utilisateur) · {{ $reception->utilisateur->nom_complet }} @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucune réception enregistrée.</p>
        @endforelse
    </section>
</x-layouts.gestionnaire>