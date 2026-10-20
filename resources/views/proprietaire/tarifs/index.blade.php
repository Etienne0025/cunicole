<x-layouts.proprietaire title="Tarifs et conditions de vente">

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    <h2 class="mb-2 font-semibold">Grille tarifaire</h2>
    <p class="mb-3 text-sm text-gray-600">
        Pour chaque catégorie, choisissez un prix au kg (poids vif pesé) ou à la pièce. Sans tarif actif, la vente de cette catégorie est impossible pour le gestionnaire.
    </p>

    @foreach ($categories as $categorie)
        @php $tarif = $tarifs->get($categorie->value); @endphp

        <form method="POST" action="{{ route('proprietaire.tarifs.update', $categorie->value) }}"
              class="mb-3 grid items-end gap-3 rounded-lg bg-white p-4 shadow md:grid-cols-5">
            @csrf @method('PUT')
            <div class="md:col-span-2">
                <div class="font-medium">{{ $categorie->libelle() }}</div>
                <div class="text-xs {{ $tarif ? 'text-gray-500' : 'text-amber-700' }}">{{ $tarif ? ($tarif->actif ? 'Tarif actif' : 'Tarif désactivé') : 'Aucun tarif défini' }}</div>
            </div>
            <select name="mode" class="rounded border-gray-300 text-sm" required>
                @foreach ($modes as $mode)
                    <option value="{{ $mode->value }}" @selected(($tarif?->mode ?? \App\Enums\ModeTarif::Kg) === $mode)>{{ $mode->libelle() }}</option>
                @endforeach
            </select>
            <input type="number" step="1" min="1" name="prix" value="{{ old('prix', $tarif?->prix !== null ? (float) $tarif->prix : '') }}" placeholder="Prix" class="rounded border-gray-300 text-sm" required>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1 text-sm">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" @checked($tarif?->actif ?? true)> Actif
                </label>
                <button class="rounded bg-blue-600 px-3 py-2 text-sm text-white">Enregistrer</button>
            </div>
        </form>
    @endforeach

    <form method="POST" action="{{ route('proprietaire.tarifs.parametres') }}" class="mt-6 rounded-lg bg-white p-5 shadow">
        @csrf @method('PUT')
        <h2 class="mb-3 font-semibold">Remises, crédit et objectifs</h2>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <x-input-label for="remise_max_pct" value="Remise maximale accordée par le gestionnaire (%)" />
                <x-text-input id="remise_max_pct" name="remise_max_pct" type="number" step="0.5" min="0" max="100" class="mt-1 block w-full" :value="old('remise_max_pct', (float) $parametres->remise_max_pct)" required />
                <p class="mt-1 text-xs text-gray-500">0 = aucune remise autorisée.</p>
            </div>
            <div>
                <x-input-label for="objectif_hebdomadaire" value="Objectif de ventes hebdomadaire" />
                <x-text-input id="objectif_hebdomadaire" name="objectif_hebdomadaire" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('objectif_hebdomadaire', $parametres->objectif_hebdomadaire !== null ? (float) $parametres->objectif_hebdomadaire : null)" />
            </div>
            <div>
                <x-input-label for="objectif_mensuel" value="Objectif de ventes mensuel" />
                <x-text-input id="objectif_mensuel" name="objectif_mensuel" type="number" step="1" min="0" class="mt-1 block w-full" :value="old('objectif_mensuel', $parametres->objectif_mensuel !== null ? (float) $parametres->objectif_mensuel : null)" />
            </div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm">
            <input type="hidden" name="credit_autorise" value="0">
            <input type="checkbox" name="credit_autorise" value="1" @checked($parametres->credit_autorise)>
            Autoriser les ventes à crédit (le client doit alors être enregistré)
        </label>

        <x-primary-button class="mt-4">Enregistrer</x-primary-button>
    </form>
</x-layouts.proprietaire>