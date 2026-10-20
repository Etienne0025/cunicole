<x-layouts.proprietaire title="Pharmacie virtuelle">

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Produits actifs</div>
            <div class="mt-1 text-3xl font-bold">{{ $produits->where('actif', true)->count() }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Stocks bas</div>
            <div class="mt-1 text-3xl font-bold {{ $produits->filter->stockBas()->isNotEmpty() ? 'text-red-600' : '' }}">
                {{ $produits->where('actif', true)->filter->stockBas()->count() }}
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('proprietaire.pharmacie.produits.store') }}" class="mb-6 rounded-lg bg-white p-5 shadow">
        @csrf
        <h2 class="mb-3 font-semibold">Nouveau produit</h2>
        <div class="grid gap-3 md:grid-cols-3">
            <input name="nom" value="{{ old('nom') }}" placeholder="Nom du produit" class="rounded border-gray-300" required>
            <select name="type" class="rounded border-gray-300" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->libelle() }}</option>
                @endforeach
            </select>
            <input name="unite" value="{{ old('unite', 'dose') }}" placeholder="Unité (dose, ml, sachet...)" class="rounded border-gray-300" required>
            <input type="number" step="0.01" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', 0) }}" placeholder="Seuil d'alerte du stock" class="rounded border-gray-300" required>
            <input type="number" min="0" max="365" name="delai_attente_jours" value="{{ old('delai_attente_jours', 0) }}" placeholder="Délai d'attente (jours)" class="rounded border-gray-300" required>
            <input name="notes" value="{{ old('notes') }}" placeholder="Notes" class="rounded border-gray-300">
        </div>
        <x-primary-button class="mt-3">Ajouter le produit</x-primary-button>
    </form>

    @forelse ($produits as $produit)
        <div class="mb-3 rounded-lg bg-white p-5 shadow {{ $produit->actif ? '' : 'opacity-60' }}" x-data="{ panneau: null }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-semibold">
                        {{ $produit->nom }}
                        <span class="text-xs font-normal text-gray-500">· {{ $produit->type->libelle() }}</span>
                        @unless ($produit->actif)<span class="text-xs text-gray-500">(désactivé)</span>@endunless
                    </div>
                    <div class="text-sm text-gray-500">Délai d'attente : {{ $produit->delai_attente_jours }} j · seuil d'alerte : {{ number_format($produit->seuil_alerte, 2, ',', ' ') }}</div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold {{ $produit->stockBas() ? 'text-red-600' : '' }}">
                        {{ number_format($produit->stock_actuel, 2, ',', ' ') }} <span class="text-sm font-normal">{{ $produit->unite }}</span>
                    </div>
                    @if ($produit->stockBas())<div class="text-xs text-red-600">Stock bas : à réapprovisionner</div>@endif
                </div>
            </div>

            <div class="mt-3 flex gap-4 text-sm">
                <button type="button" @click="panneau = panneau === 'achat' ? null : 'achat'" class="text-emerald-700 underline">Enregistrer un achat</button>
                <button type="button" @click="panneau = panneau === 'ajust' ? null : 'ajust'" class="text-amber-700 underline">Corriger l'inventaire</button>
                <button type="button" @click="panneau = panneau === 'edition' ? null : 'edition'" class="text-blue-700 underline">Modifier</button>
            </div>

            <form x-show="panneau === 'achat'" x-cloak method="POST" action="{{ route('proprietaire.pharmacie.achats.store', $produit) }}" class="mt-3 grid gap-2 md:grid-cols-5">
                @csrf
                <input type="number" step="0.01" min="0.01" name="quantite" placeholder="Quantité" class="rounded border-gray-300" required>
                <input type="date" name="date_achat" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" class="rounded border-gray-300" required>
                <input name="fournisseur" placeholder="Fournisseur" class="rounded border-gray-300">
                <input type="number" step="0.01" min="0" name="cout_total" placeholder="Coût total" class="rounded border-gray-300">
                <button class="rounded bg-emerald-600 text-white">Valider l'achat</button>
            </form>

            <form x-show="panneau === 'ajust'" x-cloak method="POST" action="{{ route('proprietaire.pharmacie.ajustements.store', $produit) }}" class="mt-3 grid gap-2 md:grid-cols-3">
                @csrf
                <input type="number" step="0.01" name="quantite" placeholder="Variation (ex : -2 ou 3)" class="rounded border-gray-300" required>
                <input name="motif" placeholder="Motif (casse, péremption, inventaire...)" class="rounded border-gray-300" required>
                <button class="rounded bg-amber-600 text-white">Corriger</button>
            </form>

            <form x-show="panneau === 'edition'" x-cloak method="POST" action="{{ route('proprietaire.pharmacie.produits.update', $produit) }}" class="mt-3 grid gap-2 md:grid-cols-3">
                @csrf @method('PUT')
                <input name="nom" value="{{ $produit->nom }}" class="rounded border-gray-300" required>
                <select name="type" class="rounded border-gray-300" required>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($produit->type === $type)>{{ $type->libelle() }}</option>
                    @endforeach
                </select>
                <input name="unite" value="{{ $produit->unite }}" class="rounded border-gray-300" required>
                <input type="number" step="0.01" min="0" name="seuil_alerte" value="{{ $produit->seuil_alerte }}" class="rounded border-gray-300" required>
                <input type="number" min="0" max="365" name="delai_attente_jours" value="{{ $produit->delai_attente_jours }}" class="rounded border-gray-300" required>
                <input name="notes" value="{{ $produit->notes }}" class="rounded border-gray-300">
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" @checked($produit->actif)> Produit actif
                </label>
                <button class="rounded bg-blue-600 py-2 text-white md:col-span-2">Enregistrer</button>
            </form>
        </div>
    @empty
        <p class="rounded-lg bg-white p-6 text-center text-gray-500 shadow">Aucun produit. Ajoutez vos vaccins et traitements ci-dessus.</p>
    @endforelse

    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Derniers mouvements de stock</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Produit</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2">Quantité</th>
                    <th class="px-4 py-2">Détail</th>
                    <th class="px-4 py-2">Par</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mouvements as $mouvement)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $mouvement->date_mouvement->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 font-medium">{{ $mouvement->produit->nom }}</td>
                        <td class="px-4 py-2">{{ $mouvement->type->libelle() }}</td>
                        <td class="px-4 py-2 {{ (float) $mouvement->quantite < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            {{ (float) $mouvement->quantite > 0 ? '+' : '' }}{{ number_format($mouvement->quantite, 2, ',', ' ') }}
                        </td>
                        <td class="px-4 py-2">
                            {{ $mouvement->motif ?? $mouvement->fournisseur }}
                            @if ($mouvement->cout_total !== null) · {{ number_format($mouvement->cout_total, 0, ',', ' ') }} @endif
                        </td>
                        <td class="px-4 py-2">{{ $mouvement->utilisateur?->nom_complet ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucun mouvement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>