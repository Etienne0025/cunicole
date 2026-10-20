@use('Illuminate\Support\Str')

<x-layouts.gestionnaire title="Nouvelle vente">

    @if ($sansTarif)
        <p class="rounded-lg bg-amber-100 p-4 text-sm text-amber-900">
            Le propriétaire n'a pas encore fixé la grille tarifaire : aucune vente n'est possible pour le moment.
        </p>
    @else
        @if ($errors->any())
            <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
                </ul>
            </div>
        @endif

        <script>
            function venteForm(c) {
                const facteur = Math.pow(10, c.decimales);
                const arrondir = (x) => Math.round(x * facteur) / facteur;
                const nombre = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: c.decimales, maximumFractionDigits: c.decimales });

                return {
                    c: c,
                    envoi: false,
                    lignes: c.lignes,
                    remise: c.remise,
                    paye: c.paye ?? '',
                    payeManuel: c.payeManuel,
                    modeClient: c.modeClient,
                    modePaiement: c.modePaiement,

                    init() {
                        this.$watch('net', (valeur) => { if (!this.payeManuel) this.paye = valeur; });
                        if (!this.payeManuel) this.paye = this.net;
                    },

                    fmt(x) { return nombre.format(x) + ' ' + c.devise; },
                    categorie(l) {
                        if (l.type === 'lot') return 'engraissement';
                        const a = this.animaux(l);
                        return a ? a.categorie : 'reproducteur';
                    },
                    animaux(l) { return c.animaux.find((x) => x.id == l.id); },
                    lot(l) { return c.lots.find((x) => x.id == l.id); },
                    tarif(l) { return c.tarifs[this.categorie(l)] || null; },
                    quantite(l) { return l.type === 'lot' ? (parseInt(l.quantite) || 0) : 1; },
                    montant(l) {
                        const t = this.tarif(l);
                        if (!t) return 0;
                        return arrondir(t.mode === 'kg' ? (parseFloat(l.poids_kg) || 0) * t.prix : this.quantite(l) * t.prix);
                    },
                    get brut() { return arrondir(this.lignes.reduce((s, l) => s + this.montant(l), 0)); },
                    get remiseMontant() { return arrondir(this.brut * (parseFloat(this.remise) || 0) / 100); },
                    get net() { return arrondir(this.brut - this.remiseMontant); },
                    get solde() { return arrondir(this.net - (parseFloat(this.paye) || 0)); },
                };
            }
        </script>

        <form method="POST" action="{{ route('gestionnaire.ventes.store') }}"
              x-data="venteForm(@js($config))" @submit="envoi = true" class="space-y-4">
            @csrf
            <input type="hidden" name="jeton" value="{{ old('jeton', (string) Str::uuid()) }}">

            {{-- Articles --}}
            <template x-for="(l, i) in lignes" :key="i">
                <div class="space-y-3 rounded-lg bg-white p-4 shadow">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" x-text="'Article ' + (i + 1)"></span>
                        <button type="button" class="text-xs text-red-600" x-show="lignes.length > 1" @click="lignes.splice(i, 1)">Retirer</button>
                    </div>

                    <select :name="`lignes[${i}][type]`" x-model="l.type" @change="l.id = ''"
                            class="block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="lot">Lapins d'un lot d'engraissement</option>
                        <option value="reproducteur">Reproducteur ou animal réformé</option>
                    </select>

                    <select :name="`lignes[${i}][id]`" x-model="l.id" x-show="l.type === 'lot'" :disabled="l.type !== 'lot'"
                            class="block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choisir un lot...</option>
                        <template x-for="lot in c.lots" :key="lot.id">
                            <option :value="lot.id" :disabled="!!lot.bloque_jusqu"
                                    x-text="lot.code + ' · ' + lot.effectif + ' lapin(s)' + (lot.bloque_jusqu ? ' · bloqué jusqu\'au ' + lot.bloque_jusqu : '')"></option>
                        </template>
                    </select>

                    <select :name="`lignes[${i}][id]`" x-model="l.id" x-show="l.type === 'reproducteur'" :disabled="l.type !== 'reproducteur'"
                            class="block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choisir un animal...</option>
                        <template x-for="a in c.animaux" :key="a.id">
                            <option :value="a.id" :disabled="!!a.bloque_jusqu"
                                    x-text="a.matricule + ' · ' + a.sexe + (a.categorie === 'reforme' ? ' · réformé' : '') + (a.cage ? ' · cage ' + a.cage : '') + (a.bloque_jusqu ? ' · bloqué jusqu\'au ' + a.bloque_jusqu : '')"></option>
                        </template>
                    </select>

                    <div x-show="l.type === 'lot'" class="space-y-1">
                        <label class="text-xs text-gray-600">Nombre de lapins vendus</label>
                        <input type="number" min="1" :max="lot(l) ? lot(l).effectif : null" :name="`lignes[${i}][quantite]`"
                               x-model.number="l.quantite" :disabled="l.type !== 'lot'"
                               class="block w-full rounded-md border-gray-300 shadow-sm">
                        <p class="text-xs text-gray-500" x-show="lot(l)" x-text="'Disponibles dans le lot : ' + (lot(l) ? lot(l).effectif : 0)"></p>
                    </div>

                    <div x-show="tarif(l) && tarif(l).mode === 'kg'" class="space-y-1">
                        <label class="text-xs text-gray-600">Poids total pesé (kg)</label>
                        <input type="number" step="0.01" min="0.1" :name="`lignes[${i}][poids_kg]`" x-model="l.poids_kg"
                               :disabled="!(tarif(l) && tarif(l).mode === 'kg')" :required="tarif(l) && tarif(l).mode === 'kg'"
                               class="block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <p class="text-xs text-red-600" x-show="!tarif(l)">
                        Le propriétaire n'a pas fixé de tarif pour cette catégorie : la vente sera refusée.
                    </p>
                    <p class="text-sm" x-show="tarif(l)">
                        Prix : <span x-text="tarif(l) ? fmt(tarif(l).prix) + (tarif(l).mode === 'kg' ? ' / kg' : ' / pièce') : ''"></span>
                        · Montant : <strong x-text="fmt(montant(l))"></strong>
                    </p>
                </div>
            </template>

            <button type="button" class="w-full rounded border bg-white py-2 text-sm"
                    @click="lignes.push({ type: 'lot', id: '', quantite: 1, poids_kg: '' })">+ Ajouter un autre article</button>

            {{-- Client --}}
            <div class="space-y-3 rounded-lg bg-white p-4 shadow">
                <h2 class="font-semibold">Client</h2>

                <select x-model="modeClient" class="block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="passage">Client de passage</option>
                    <option value="existant">Client enregistré</option>
                    <option value="nouveau">Nouveau client</option>
                </select>

                <select name="client_id" x-show="modeClient === 'existant'" :disabled="modeClient !== 'existant'"
                        class="block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Choisir...</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->nom }}@if ($client->telephone) · {{ $client->telephone }}@endif</option>
                    @endforeach
                </select>

                <div x-show="modeClient === 'nouveau'" class="grid grid-cols-2 gap-2">
                    <input name="nouveau_client_nom" value="{{ old('nouveau_client_nom') }}" maxlength="100" placeholder="Nom du client"
                           :disabled="modeClient !== 'nouveau'" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <input name="nouveau_client_telephone" value="{{ old('nouveau_client_telephone') }}" maxlength="30" placeholder="Téléphone"
                           :disabled="modeClient !== 'nouveau'" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>
            </div>

            {{-- Remise --}}
            <div class="space-y-1 rounded-lg bg-white p-4 shadow" x-show="c.remise_max > 0">
                <label class="text-sm font-semibold">Remise (%)</label>
                <input type="number" step="0.5" min="0" :max="c.remise_max" name="remise_pct" x-model="remise" :disabled="c.remise_max <= 0"
                       class="block w-full rounded-md border-gray-300 shadow-sm">
                <p class="text-xs text-gray-500">Remise maximale autorisée : <span x-text="c.remise_max"></span> %.</p>
            </div>

            {{-- Paiement --}}
            <div class="space-y-3 rounded-lg bg-white p-4 shadow">
                <h2 class="font-semibold">Paiement</h2>

                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>Total brut</dt><dd x-text="fmt(brut)"></dd></div>
                    <div class="flex justify-between text-amber-700" x-show="remiseMontant > 0"><dt>Remise</dt><dd x-text="'− ' + fmt(remiseMontant)"></dd></div>
                    <div class="flex justify-between text-lg font-bold"><dt>À payer</dt><dd x-text="fmt(net)"></dd></div>
                </dl>

                <div>
                    <label class="text-xs text-gray-600">Montant payé</label>
                    <div class="flex gap-2">
                        <input type="number" step="0.01" min="0" name="montant_paye" x-model="paye" @input="payeManuel = true" required
                               class="block w-full rounded-md border-gray-300 shadow-sm">
                        <button type="button" class="shrink-0 rounded border px-3 text-sm" @click="paye = net; payeManuel = false">Tout payé</button>
                    </div>
                </div>

                <p class="text-sm" x-show="solde > 0" :class="c.credit ? 'text-amber-700' : 'text-red-700'">
                    Reste à payer (crédit) : <strong x-text="fmt(solde)"></strong>
                    <span x-show="!c.credit"> : le crédit n'est pas autorisé, le paiement doit couvrir tout le montant.</span>
                    <span x-show="c.credit && modeClient === 'passage'"> : choisissez ou créez un client.</span>
                </p>

                <select name="mode_paiement" x-model="modePaiement" class="block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="especes">Espèces</option>
                    <option value="mobile_money">Mobile money</option>
                </select>

                <div x-show="modePaiement === 'mobile_money'" class="grid grid-cols-2 gap-2">
                    <input name="operateur" value="{{ old('operateur') }}" maxlength="50" placeholder="Opérateur (nom libre)"
                           :disabled="modePaiement !== 'mobile_money'" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <input name="reference" value="{{ old('reference') }}" maxlength="100" placeholder="Référence de la transaction"
                           :disabled="modePaiement !== 'mobile_money'" class="rounded-md border-gray-300 text-sm shadow-sm">
                </div>

                <textarea name="notes" rows="2" maxlength="500" placeholder="Notes (facultatif)"
                          class="block w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('notes') }}</textarea>
            </div>

            <button :disabled="envoi || net <= 0" class="w-full rounded bg-emerald-600 py-3 text-white disabled:opacity-50">
                <span x-show="!envoi">Enregistrer la vente</span>
                <span x-show="envoi" x-cloak>Enregistrement...</span>
            </button>
            <p class="text-center text-xs text-gray-500">Les montants affichés sont indicatifs : le serveur recalcule et vérifie tout avant d'enregistrer.</p>
        </form>
    @endif
</x-layouts.gestionnaire>