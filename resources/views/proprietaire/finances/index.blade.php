@use('App\Support\Monnaie')

<x-layouts.proprietaire title="Tableau financier">

    <div class="mb-2 flex flex-wrap gap-2 text-sm">
        @foreach (['mois' => 'Mois en cours', 'mois_precedent' => 'Mois précédent', 'trimestre' => '3 derniers mois', 'annee' => 'Année en cours'] as $cle => $libelle)
            <a href="{{ route('proprietaire.finances', ['periode' => $cle]) }}"
               class="rounded px-3 py-1 {{ $periode['cle'] === $cle ? 'bg-slate-800 text-white' : 'border bg-white' }}">{{ $libelle }}</a>
        @endforeach
    </div>
    <p class="mb-4 text-sm text-gray-500">Du {{ $periode['debut']->format('d/m/Y') }} au {{ $periode['fin']->format('d/m/Y') }}</p>

    <div class="mb-4 grid gap-4 md:grid-cols-4">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Chiffre d'affaires</div>
            <div class="mt-1 text-2xl font-bold">{{ Monnaie::format($bilan['ca']) }}</div>
            <div class="text-xs text-gray-500">{{ $bilan['nb_ventes'] }} vente(s) · {{ $bilan['nb_animaux'] }} animal(aux)</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Charges</div>
            <div class="mt-1 text-2xl font-bold">{{ Monnaie::format($bilan['charges']['total']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Bénéfice net</div>
            <div class="mt-1 text-2xl font-bold {{ $bilan['benefice'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ Monnaie::format($bilan['benefice']) }}</div>
            <div class="text-xs text-gray-500">= Chiffre d'affaires − Charges</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Marge nette</div>
            <div class="mt-1 text-2xl font-bold">{{ $bilan['marge'] !== null ? number_format($bilan['marge'], 1, ',', ' ').' %' : '-' }}</div>
        </div>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Encaissé sur la période</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($bilan['encaisse']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Créances à recouvrer (toutes ventes)</div>
            <div class="mt-1 text-xl font-bold {{ $creances > 0 ? 'text-red-600' : '' }}">{{ Monnaie::format($creances) }}</div>
            <a href="{{ route('proprietaire.ventes.index', ['statut' => 'impayees', 'periode' => 'annee']) }}" class="text-xs text-blue-600 underline">Voir les ventes impayées</a>
        </div>
        <div class="rounded-lg bg-white p-5 shadow">
            <div class="text-sm text-gray-500">Remises accordées</div>
            <div class="mt-1 text-xl font-bold">{{ Monnaie::format($bilan['remises']) }}</div>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">6 derniers mois</h2>
            <canvas id="graphique-finances" height="150"></canvas>
        </div>

        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Objectifs de vente</h2>
            @foreach ([['Cette semaine', $objectifs['semaine']], ['Ce mois', $objectifs['mois']]] as [$libelle, $o])
                <div class="mb-4 last:mb-0">
                    <div class="flex justify-between text-sm">
                        <span>{{ $libelle }}</span>
                        <span class="font-medium">{{ Monnaie::format($o['realise']) }}@if ($o['objectif']) / {{ Monnaie::format($o['objectif']) }}@endif</span>
                    </div>
                    @if ($o['pourcentage'] !== null)
                        <div class="mt-1 h-3 rounded bg-gray-200">
                            <div class="h-3 rounded {{ $o['pourcentage'] >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ min(100, $o['pourcentage']) }}%"></div>
                        </div>
                        <div class="text-right text-xs text-gray-500">{{ $o['pourcentage'] }} %</div>
                    @else
                        <p class="text-xs text-gray-500">Aucun objectif fixé.</p>
                    @endif
                </div>
            @endforeach
            <a href="{{ route('proprietaire.tarifs') }}" class="text-sm text-blue-600 underline">Modifier les objectifs</a>
        </div>
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <h2 class="px-4 pt-4 font-semibold">Détail des charges</h2>
            <table class="mt-2 min-w-full text-sm">
                <tbody>
                    @forelse ($bilan['charges']['detail'] as $ligne)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $ligne['libelle'] }}</td>
                            <td class="px-4 py-2 text-right">{{ Monnaie::format($ligne['montant']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-gray-500">Aucune charge sur la période.</td></tr>
                    @endforelse
                    <tr class="border-t font-bold">
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right">{{ Monnaie::format($bilan['charges']['total']) }}</td>
                    </tr>
                </tbody>
            </table>
            @if ($bilan['charges']['sans_montant'] > 0)
                <p class="px-4 pb-4 text-xs text-amber-700">
                    {{ $bilan['charges']['sans_montant'] }} réception(s) d'aliment ou achat(s) de pharmacie sans montant ne sont pas comptés dans les charges.
                </p>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <h2 class="px-4 pt-4 font-semibold">Ventes par catégorie</h2>
            <table class="mt-2 min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Catégorie</th>
                        <th class="px-4 py-2">Animaux</th>
                        <th class="px-4 py-2">Poids</th>
                        <th class="px-4 py-2 text-right">Montant brut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bilan['ventes_par_categorie'] as $ligne)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $ligne->categorie->libelle() }}</td>
                            <td class="px-4 py-2">{{ (int) $ligne->animaux }}</td>
                            <td class="px-4 py-2">{{ (float) $ligne->poids > 0 ? number_format((float) $ligne->poids, 1, ',', ' ').' kg' : '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ Monnaie::format($ligne->montant) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Aucune vente sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="px-4 pb-4 pt-2 text-xs text-gray-500">Montants avant remise.</p>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new Chart(document.getElementById('graphique-finances'), {
                    type: 'bar',
                    data: {
                        labels: @json($serie->pluck('label')->values()),
                        datasets: [
                            { type: 'bar', label: "Chiffre d'affaires", data: @json($serie->pluck('ca')->values()) },
                            { type: 'bar', label: 'Charges', data: @json($serie->pluck('charges')->values()) },
                            { type: 'line', label: 'Bénéfice net', data: @json($serie->pluck('benefice')->values()), tension: 0.3 },
                        ],
                    },
                    options: { scales: { y: { beginAtZero: true } } },
                });
            });
        </script>
    @endpush
</x-layouts.proprietaire>