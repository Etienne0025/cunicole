<x-layouts.proprietaire title="Calendrier prophylactique">

    <p class="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-900">
        Ce calendrier est celui prescrit par votre vétérinaire. L'application ne fixe aucun protocole médical :
        elle génère les rappels et les tâches du gestionnaire à partir de ce que vous saisissez ici.
        Les intervalles sont comptés en jours, à partir de la dernière dose enregistrée.
    </p>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($vaccins->isEmpty())
        <p class="mb-6 rounded-lg bg-white p-5 text-sm shadow">
            Aucun vaccin dans la pharmacie. Ajoutez d'abord un produit de type « Vaccin » dans la
            <a href="{{ route('proprietaire.pharmacie') }}" class="text-blue-600 underline">pharmacie</a>.
        </p>
    @else
        <form method="POST" action="{{ route('proprietaire.protocoles.store') }}" class="mb-6 rounded-lg bg-white p-5 shadow">
            @csrf
            <h2 class="mb-3 font-semibold">Nouveau protocole</h2>
            <div class="grid gap-3 md:grid-cols-3">
                @include('proprietaire.protocoles._champs', ['protocole' => null])
            </div>
            <x-primary-button class="mt-3">Ajouter au calendrier</x-primary-button>
        </form>
    @endif

    @forelse ($protocoles as $protocole)
        <div class="mb-3 rounded-lg bg-white p-5 shadow {{ $protocole->actif ? '' : 'opacity-60' }}" x-data="{ edition: false }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-semibold">{{ $protocole->nom }} @unless ($protocole->actif)<span class="text-xs font-normal text-gray-500">(désactivé)</span>@endunless</div>
                    <div class="text-sm text-gray-500">
                        {{ $protocole->produit->nom }} · {{ $protocole->cible->libelle() }}
                        · 1re dose à {{ $protocole->age_premiere_dose_jours }} j
                        · {{ $protocole->intervalle_rappel_jours ? 'rappel tous les '.$protocole->intervalle_rappel_jours.' j' : 'dose unique' }}
                        · {{ rtrim(rtrim(number_format($protocole->doses_par_animal, 2, ',', ''), '0'), ',') }} dose(s) par animal
                    </div>
                </div>
                <button type="button" @click="edition = !edition" class="text-sm text-blue-700 underline">Modifier</button>
            </div>

            <form x-show="edition" x-cloak method="POST" action="{{ route('proprietaire.protocoles.update', $protocole) }}" class="mt-3 grid gap-3 md:grid-cols-3">
                @csrf @method('PUT')
                @include('proprietaire.protocoles._champs', ['protocole' => $protocole])
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" value="1" @checked($protocole->actif)> Protocole actif
                </label>
                <button class="rounded bg-blue-600 py-2 text-white md:col-span-2">Enregistrer</button>
            </form>
        </div>
    @empty
        <p class="rounded-lg bg-white p-6 text-center text-gray-500 shadow">Aucun protocole saisi.</p>
    @endforelse
</x-layouts.proprietaire>