<x-layouts.proprietaire title="Formules de rationnement">

    <p class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">
        Les quantités sont des grammes par jour et par animal, à définir selon vos aliments et les conseils de votre technicien ou vétérinaire.
        L'application ne fixe aucune ration : elle calcule la quantité théorique du jour à partir de ce que vous saisissez ici
        et de l'effectif réel de chaque catégorie. La ration d'une femelle allaitante inclut sa portée.
    </p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($aliments->isEmpty())
        <p class="mb-6 rounded-lg bg-white p-5 text-sm shadow">
            Aucun aliment actif. Créez d'abord vos aliments dans
            <a href="{{ route('proprietaire.aliments') }}" class="text-blue-600 underline">Aliments et stocks</a>.
        </p>
    @endif

    @foreach ($categories as $categorie)
        @php
            $lignes = $rations->get($categorie->value, collect());
            $nombre = $effectifs[$categorie->value] ?? 0;
        @endphp

        <div class="mb-4 rounded-lg bg-white p-5 shadow">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ $categorie->libelle() }}</h2>
                <span class="text-sm text-gray-500">Effectif actuel : <strong>{{ $nombre }}</strong></span>
            </div>

            @forelse ($lignes as $ration)
                <form method="POST" action="{{ route('proprietaire.rations.update', $ration) }}"
                      class="mt-3 grid items-end gap-3 border-t pt-3 md:grid-cols-5 {{ $ration->actif ? '' : 'opacity-60' }}">
                    @csrf @method('PUT')
                    <div class="md:col-span-2">
                        <div class="font-medium">{{ $ration->aliment->nom }}</div>
                        <div class="text-xs text-gray-500">
                            ≈ {{ number_format($ration->quantiteTheorique($nombre), 2, ',', ' ') }} kg par jour pour {{ $nombre }} animal(aux)
                        </div>
                    </div>
                    <label class="text-xs text-gray-500">
                        Grammes par jour {{ $categorie->parUnite() }}
                        <input type="number" min="1" max="5000" name="grammes_par_jour" value="{{ $ration->grammes_par_jour }}"
                               class="mt-1 block w-full rounded border-gray-300 text-sm" required>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="actif" value="0">
                        <input type="checkbox" name="actif" value="1" @checked($ration->actif)> Active
                    </label>
                    <button class="rounded bg-blue-600 py-2 text-white">Enregistrer</button>
                </form>
            @empty
                <p class="mt-3 text-sm text-gray-500">Aucun aliment défini : aucune tâche de distribution ne sera générée pour cette catégorie.</p>
            @endforelse

            @if ($aliments->isNotEmpty())
                <form method="POST" action="{{ route('proprietaire.rations.store') }}" class="mt-4 grid gap-2 border-t pt-3 md:grid-cols-4">
                    @csrf
                    <input type="hidden" name="categorie" value="{{ $categorie->value }}">
                    <select name="aliment_id" class="rounded border-gray-300 text-sm" required>
                        <option value="">Ajouter un aliment...</option>
                        @foreach ($aliments as $aliment)
                            <option value="{{ $aliment->id }}">{{ $aliment->nom }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" max="5000" name="grammes_par_jour" placeholder="g par jour {{ $categorie->parUnite() }}" class="rounded border-gray-300 text-sm" required>
                    <input name="notes" maxlength="200" placeholder="Notes (facultatif)" class="rounded border-gray-300 text-sm">
                    <button class="rounded bg-emerald-600 py-2 text-white">Ajouter à la formule</button>
                </form>
            @endif
        </div>
    @endforeach
</x-layouts.proprietaire>