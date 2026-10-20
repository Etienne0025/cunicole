<x-layouts.gestionnaire title="Santé et soins">

    <section class="mb-4 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">
            Vaccinations à faire aujourd'hui
            <span class="rounded-full bg-gray-100 px-2 text-sm">{{ $vaccinations->count() }}</span>
        </h2>
        @forelse ($vaccinations as $tache)
            <div class="flex items-center justify-between gap-2 border-b py-2 text-sm last:border-0">
                <div>
                    <div class="font-medium">{{ $tache->titre }}</div>
                    <div class="text-xs text-gray-500">{{ $tache->description }}</div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <form method="POST" action="{{ route('gestionnaire.taches.cocher', $tache) }}">
                        @csrf
                        <button class="rounded bg-emerald-600 px-3 py-2 text-white">Vaccin fait</button>
                    </form>
                    @if ($lien = $tache->lien())
                        <a href="{{ $lien }}" class="text-xs text-blue-600 underline">Ouvrir</a>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucune vaccination prévue aujourd'hui.</p>
        @endforelse
    </section>

    <section class="mb-4 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">
            Traitements en cours
            <span class="rounded-full bg-gray-100 px-2 text-sm">{{ $traitements->count() }}</span>
        </h2>
        @forelse ($traitements as $traitement)
            <a href="{{ $traitement->lien() }}" class="block border-b py-2 text-sm last:border-0">
                <div class="font-medium">{{ $traitement->libelleCible() }} · {{ $traitement->nom_traitement }}</div>
                <div class="text-xs text-gray-500">
                    Du {{ $traitement->date_debut->format('d/m') }} au {{ $traitement->date_fin_traitement->format('d/m') }}
                    · {{ $traitement->symptomes }}
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucun traitement en cours.</p>
        @endforelse
    </section>

    <section class="mb-4 rounded-lg border border-red-200 bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold text-red-700">
            Bloqués pour la vente
            <span class="rounded-full bg-red-100 px-2 text-sm">{{ $bloques->count() }}</span>
        </h2>
        @forelse ($bloques as $traitement)
            <a href="{{ $traitement->lien() }}" class="flex justify-between border-b py-2 text-sm last:border-0">
                <span>{{ $traitement->libelleCible() }}</span>
                <span class="text-red-700">jusqu'au {{ $traitement->date_fin_attente->format('d/m/Y') }}</span>
            </a>
        @empty
            <p class="text-sm text-gray-500">Aucun animal bloqué.</p>
        @endforelse
    </section>

    <section class="mb-4 rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Derniers décès</h2>
        @forelse ($deces as $mortalite)
            <div class="border-b py-2 text-sm last:border-0">
                <div class="flex justify-between">
                    <span class="font-medium">{{ $mortalite->code_cible }}</span>
                    <span class="text-gray-500">{{ $mortalite->date_deces->format('d/m/Y') }}</span>
                </div>
                <div class="text-xs text-gray-500">
                    {{ $mortalite->nombre }} · {{ $mortalite->cause->libelle() }}
                    @if ($mortalite->precisions) · {{ $mortalite->precisions }} @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">Aucun décès enregistré.</p>
        @endforelse
        <p class="mt-3 text-xs text-gray-500">
            Pour déclarer un décès, ouvrez la fiche de l'animal, du lot ou de la portée (par exemple en scannant le QR de la cage).
        </p>
    </section>

    <section class="rounded-lg bg-white p-4 shadow">
        <h2 class="mb-2 font-semibold">Stock de la pharmacie</h2>
        @forelse ($produits as $produit)
            <div class="flex justify-between border-b py-2 text-sm last:border-0">
                <span>{{ $produit->nom }}</span>
                <span class="{{ $produit->stockBas() ? 'font-semibold text-red-600' : '' }}">
                    {{ number_format($produit->stock_actuel, 2, ',', ' ') }} {{ $produit->unite }}
                    @if ($produit->stockBas()) · stock bas @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-500">La pharmacie est vide. Le propriétaire doit enregistrer les achats.</p>
        @endforelse
    </section>
</x-layouts.gestionnaire>