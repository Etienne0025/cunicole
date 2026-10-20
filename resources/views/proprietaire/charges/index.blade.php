@use('App\Support\Monnaie')

<x-layouts.proprietaire title="Charges">

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['mois' => 'Mois en cours', 'mois_precedent' => 'Mois précédent', 'trimestre' => '3 derniers mois', 'annee' => 'Année en cours'] as $cle => $libelle)
            <a href="{{ route('proprietaire.charges.index', ['periode' => $cle]) }}"
               class="rounded px-3 py-1 {{ $periode['cle'] === $cle ? 'bg-slate-800 text-white' : 'border bg-white' }}">{{ $libelle }}</a>
        @endforeach
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Charges fixes</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($totaux['fixes']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Dépenses courantes</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($totaux['courantes']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Aliments (réceptions)</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($totaux['aliments']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Pharmacie (achats)</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($totaux['pharmacie']) }}</div>
        </div>
    </div>

    <p class="mb-4 text-xs text-gray-500">
        Les achats d'aliments et de pharmacie sont repris automatiquement depuis leur module (avec leur montant) : ne les saisissez pas ici.
    </p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('proprietaire.charges.store') }}" class="mb-6 rounded-lg bg-white p-5 shadow">
        @csrf
        <h2 class="mb-3 font-semibold">Nouvelle charge</h2>
        <div class="grid gap-3 md:grid-cols-5">
            <select name="categorie" class="rounded border-gray-300" required>
                <option value="">Catégorie...</option>
                <optgroup label="Charges fixes">
                    @foreach ($fixes as $categorie)
                        <option value="{{ $categorie->value }}" @selected(old('categorie') === $categorie->value)>{{ $categorie->libelle() }}</option>
                    @endforeach
                </optgroup>
                <optgroup label="Dépenses courantes">
                    @foreach ($courantes as $categorie)
                        <option value="{{ $categorie->value }}" @selected(old('categorie') === $categorie->value)>{{ $categorie->libelle() }}</option>
                    @endforeach
                </optgroup>
            </select>
            <input name="libelle" value="{{ old('libelle') }}" maxlength="150" placeholder="Désignation (ex : salaire d'octobre)" class="rounded border-gray-300 md:col-span-2" required>
            <input type="number" step="0.01" min="0.01" name="montant" value="{{ old('montant') }}" placeholder="Montant" class="rounded border-gray-300" required>
            <input type="date" name="date_charge" value="{{ old('date_charge', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="rounded border-gray-300" required>
        </div>
        <x-primary-button class="mt-3">Enregistrer la charge</x-primary-button>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <h2 class="px-4 pt-4 font-semibold">Charges saisies sur la période</h2>
        <table class="mt-2 min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Catégorie</th>
                    <th class="px-4 py-2">Désignation</th>
                    <th class="px-4 py-2">Saisi par</th>
                    <th class="px-4 py-2 text-right">Montant</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($charges as $charge)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $charge->date_charge->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">{{ $charge->categorie->libelle() }}</td>
                        <td class="px-4 py-2">{{ $charge->libelle }}</td>
                        <td class="px-4 py-2">{{ $charge->utilisateur?->nom_complet ?? '-' }}</td>
                        <td class="px-4 py-2 text-right">{{ Monnaie::format($charge->montant) }}</td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('proprietaire.charges.destroy', $charge) }}" onsubmit="return confirm('Supprimer cette charge ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 underline">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Aucune charge saisie sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.proprietaire>