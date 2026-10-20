<x-layouts.gestionnaire :title="'Vente '.$vente->numero">
    <div class="space-y-4">
        @include('ventes._detail', ['vente' => $vente])

        <a href="{{ route('gestionnaire.ventes.recu', $vente) }}" target="_blank"
           class="block rounded bg-slate-800 py-3 text-center text-white">Reçu (PDF) à remettre au client</a>

        @if ($vente->solde() > 0)
            <form method="POST" action="{{ route('gestionnaire.ventes.paiements.store', $vente) }}"
                  x-data="{ mode: '{{ old('mode_paiement', 'especes') }}' }"
                  class="space-y-3 rounded-lg border border-red-200 bg-white p-4 shadow">
                @csrf
                <h2 class="font-semibold text-red-700">Encaisser un paiement</h2>

                <div>
                    <label class="text-xs text-gray-600">Montant reçu (maximum : {{ \App\Support\Monnaie::format($vente->solde()) }})</label>
                    <input type="number" step="0.01" min="0.01" name="montant" value="{{ old('montant', $vente->solde()) }}" required
                           class="block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error :messages="$errors->get('montant')" class="mt-1" />
                </div>

                <select name="mode_paiement" x-model="mode" class="block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="especes">Espèces</option>
                    <option value="mobile_money">Mobile money</option>
                </select>
                <x-input-error :messages="$errors->get('mode_paiement')" />

                <div x-show="mode === 'mobile_money'" class="grid grid-cols-2 gap-2">
                    <input name="operateur" maxlength="50" placeholder="Opérateur" :disabled="mode !== 'mobile_money'" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <input name="reference" maxlength="100" placeholder="Référence" :disabled="mode !== 'mobile_money'" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>

                <button class="w-full rounded bg-emerald-600 py-3 text-white">Enregistrer le paiement</button>
            </form>
        @endif

        <a href="{{ route('gestionnaire.ventes.index') }}" class="block text-center text-sm text-blue-600 underline">← Retour aux ventes</a>
    </div>
</x-layouts.gestionnaire>