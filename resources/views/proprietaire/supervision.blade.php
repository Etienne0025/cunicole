<x-layouts.proprietaire title="Supervision du jour">

    @foreach ($joursNonClotures as $jourPasse)
        <a href="{{ route('proprietaire.rapports.show', $jourPasse) }}"
           class="mb-3 block rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800">
            La journée du {{ \Illuminate\Support\Carbon::parse($jourPasse)->format('d/m/Y') }} n'a pas été clôturée par le gestionnaire.
        </a>
    @endforeach

    <div x-data="supervision(@js($etat), '{{ route('proprietaire.supervision.progression') }}')" x-init="demarrer()">

        <div class="mb-6 rounded-lg bg-white p-5 shadow">
            <div class="flex items-end justify-between">
                <div>
                    <div class="text-sm capitalize text-gray-500" x-text="etat.date"></div>
                    <div class="text-4xl font-bold"><span x-text="etat.pourcentage ?? 0"></span> %</div>
                </div>
                <div class="text-right text-sm text-gray-500">
                    <div><span x-text="etat.faites"></span> faites sur <span x-text="etat.total"></span></div>
                    <div>
                        Actualisé à <span x-text="etat.actualise_a"></span>
                        <span x-show="erreur" x-cloak class="text-red-600">(connexion perdue)</span>
                    </div>
                </div>
            </div>
            <div class="mt-3 h-4 rounded bg-gray-200">
                <div class="h-4 rounded bg-emerald-500 transition-all"
                     :style="'width: ' + (etat.pourcentage ?? 0) + '%'"></div>
            </div>
            <p x-show="etat.cloturee" x-cloak class="mt-3 text-sm text-emerald-700">Journée clôturée par le gestionnaire.</p>
        </div>

        <div class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">À faire</div>
                <div class="mt-1 text-3xl font-bold" x-text="etat.restantes"></div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">Faites</div>
                <div class="mt-1 text-3xl font-bold text-emerald-700" x-text="etat.faites"></div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <div class="text-sm text-gray-500">Non faites (justifiées)</div>
                <div class="mt-1 text-3xl font-bold text-red-600" x-text="etat.non_faites"></div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Tâche</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Statut</th>
                        <th class="px-4 py-2">Heure</th>
                        <th class="px-4 py-2">Fait par</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="tache in etat.taches" :key="tache.id">
                        <tr class="border-t">
                            <td class="px-4 py-2">
                                <div class="font-medium" x-text="tache.titre"></div>
                                <div class="text-xs text-gray-500" x-text="tache.description"></div>
                                <div class="text-xs text-red-700" x-show="tache.justification" x-text="tache.justification"></div>
                            </td>
                            <td class="px-4 py-2" x-text="tache.type"></td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="classes[tache.statut]" x-text="tache.statut_libelle"></span>
                            </td>
                            <td class="px-4 py-2" x-text="tache.heure ?? '-'"></td>
                            <td class="px-4 py-2" x-text="tache.par ?? '-'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <p x-show="etat.taches.length === 0" x-cloak class="p-6 text-center text-gray-500">Aucune tâche aujourd'hui.</p>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('supervision', (etatInitial, url) => ({
                    etat: etatInitial,
                    erreur: false,
                    classes: {
                        a_faire: 'bg-amber-100 text-amber-800',
                        faite: 'bg-emerald-100 text-emerald-800',
                        non_faite: 'bg-red-100 text-red-800',
                    },
                    demarrer() {
                        setInterval(() => this.actualiser(), 30000);
                    },
                    async actualiser() {
                        try {
                            const reponse = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            if (!reponse.ok) throw new Error('Réponse invalide');
                            this.etat = await reponse.json();
                            this.erreur = false;
                        } catch (e) {
                            this.erreur = true;
                        }
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.proprietaire>