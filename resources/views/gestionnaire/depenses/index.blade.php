@use('App\Support\Monnaie')

<x-layouts.gestionnaire title="Dépenses">
    <form method="POST" action="{{ route('gestionnaire.depenses.store') }}" data-hl-reset class="mb-4 space-y-3 rounded-lg bg-white p-4 shadow">
        @csrf
        <h2 class="font-semibold">Nouvelle dépense</h2>

        <div>
            <x-input-label for="categorie" value="Catégorie" />
            <select id="categorie" name="categorie" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="">Choisir...</option>
                @foreach ($categories as $categorie)
                    <option value="{{ $categorie->value }}" @selected(old('categorie') === $categorie->value)>{{ $categorie->libelle() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('categorie')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="libelle" value="Désignation (ex : 2 bottes de paille)" />
            <x-text-input id="libelle" name="libelle" class="mt-1 block w-full" :value="old('libelle')" maxlength="150" required />
            <x-input-error :messages="$errors->get('libelle')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label for="montant" value="Montant" />
                <input id="montant" type="number" step="0.01" min="0.01" name="montant" value="{{ old('montant') }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <x-input-error :messages="$errors->get('montant')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="date_charge" value="Date" />
                <input id="date_charge" type="date" name="date_charge" value="{{ old('date_charge', today()->toDateString()) }}"
                       min="{{ today()->subDays(7)->toDateString() }}" max="{{ today()->toDateString() }}" required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <x-input-error :messages="$errors->get('date_charge')" class="mt-1" />
            </div>
        </div>

        <button class="w-full rounded bg-emerald-600 py-3 text-white">Enregistrer la dépense</button>
    </form>

    <section class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 flex justify-between font-semibold">
            <span>Dépenses des 30 derniers jours</span>
            <span class="text-sm font-normal text-gray-500">Ce mois : {{ Monnaie::format($totalMois) }}</span>
        </h2>

        @forelse ($charges as $charge)
            <div class="flex items-start justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                    <div class="font-medium">{{ $charge->libelle }}</div>
                    <div class="text-xs text-gray-500">
                        {{ $charge->date_charge->format('d/m/Y') }} · {{ $charge->categorie->libelle() }} · {{ $charge->utilisateur?->nom_complet ?? '-' }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-medium">{{ Monnaie::format($charge->montant) }}</div>
                    @if ($charge->utilisateur_id === auth()->id() && $charge->created_at->isToday())
                        <form method="POST" action="{{ route('gestionnaire.depenses.destroy', $charge) }}"
                              onsubmit="return confirm('Annuler cette dépense ?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-600 underline">Annuler</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucune dépense enregistrée.</p>
        @endforelse
    </section>
</x-layouts.gestionnaire>