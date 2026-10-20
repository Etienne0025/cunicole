<x-layouts.proprietaire title="Cartographie de la ferme">

    <p id="etat-connexion" class="mb-3 hidden rounded bg-red-50 p-2 text-sm text-red-700">
        Connexion perdue : les données affichées ne sont plus à jour. Rechargez la page.
    </p>

    <div id="carte">
        <div class="mb-6 grid gap-4 md:grid-cols-5">
            @foreach ([
                ['Cages enregistrées', $stats['total']],
                ['Cages activées', $stats['activees']],
                ['Occupées', $stats['occupees']],
                ['Vides (disponibles)', $stats['vides']],
                ['À nettoyer', $stats['a_nettoyer']],
            ] as [$libelle, $valeur])
                <div class="rounded-lg bg-white p-5 shadow">
                    <div class="text-sm text-gray-500">{{ $libelle }}</div>
                    <div class="mt-1 text-3xl font-bold">{{ $valeur }}</div>
                </div>
            @endforeach
        </div>

        <p class="mb-4 text-sm text-gray-600">
            Capacité d'accueil totale des cages activées : <strong>{{ $stats['capacite'] }}</strong> animaux.
            <span class="text-xs text-gray-400">Actualisé à {{ now()->format('H:i:s') }}</span>
        </p>

        <div class="mb-4 flex flex-wrap gap-4 text-xs">
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-gray-200"></span> Non activée</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-emerald-500"></span> Vide</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-blue-600"></span> Occupée</span>
            <span class="flex items-center gap-1"><span class="h-3 w-3 rounded bg-amber-400"></span> À nettoyer</span>
        </div>

        @forelse ($batiments as $batiment)
            <section class="mb-6 rounded-lg bg-white p-5 shadow">
                <h2 class="mb-3 font-semibold">
                    {{ $batiment->nom }}
                    <span class="text-sm font-normal text-gray-500">({{ $batiment->cages->count() }} cages)</span>
                </h2>

                <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-10">
                    @foreach ($batiment->cages as $cage)
                        @php
                            $couleur = match ($cage->etat()) {
                                'inactive' => 'bg-gray-200 text-gray-600',
                                'vide' => 'bg-emerald-500 text-white',
                                'occupee' => 'bg-blue-600 text-white',
                                'a_nettoyer' => 'bg-amber-400 text-gray-900',
                            };
                        @endphp
                        <div class="rounded p-2 text-center text-xs font-medium {{ $couleur }}" title="{{ $cage->type->libelle() }}">
                            {{ $cage->code }}
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-gray-500">Aucun bâtiment n'a encore été créé par le gestionnaire.</p>
        @endforelse
    </div>

    @push('scripts')
        <script>
            async function rafraichirCarte() {
                const alerte = document.getElementById('etat-connexion');

                try {
                    const reponse = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!reponse.ok) throw new Error('Réponse invalide');

                    const page = new DOMParser().parseFromString(await reponse.text(), 'text/html');
                    const nouvelle = page.getElementById('carte');
                    if (!nouvelle) throw new Error('Session expirée');

                    document.getElementById('carte').innerHTML = nouvelle.innerHTML;
                    alerte.classList.add('hidden');
                } catch (erreur) {
                    alerte.classList.remove('hidden');
                }
            }

            setInterval(rafraichirCarte, 30000);
        </script>
    @endpush
</x-layouts.proprietaire>