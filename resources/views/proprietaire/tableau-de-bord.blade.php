@use('App\Support\Monnaie')

@php
    $niveaux = [
        'critique' => ['border-red-300 bg-red-50 text-red-800', 'Urgent'],
        'avertissement' => ['border-amber-300 bg-amber-50 text-amber-900', 'À surveiller'],
        'information' => ['border-blue-200 bg-blue-50 text-blue-900', 'Information'],
    ];
    $mort = $d['sante']['mortalite'];
    $fin = $d['finances'];
    $prog = $d['journee']['progression'];
    $repro = $d['reproduction'];
@endphp

<x-layouts.proprietaire title="Tableau de bord">

    {{-- Indicateurs clés --}}
    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('proprietaire.cartographie') }}" class="rounded-lg bg-white p-5 shadow hover:ring-2 hover:ring-slate-300">
            <div class="text-sm text-gray-500">Cages occupées</div>
            <div class="mt-1 text-3xl font-bold">{{ $d['cages']['occupees'] }}<span class="text-base font-normal text-gray-500"> / {{ $d['cages']['activees'] }}</span></div>
            <div class="text-xs text-gray-500">{{ $d['cages']['vides'] }} vide(s) · {{ $d['cages']['a_nettoyer'] }} à nettoyer</div>
        </a>

        <a href="{{ route('proprietaire.reproduction') }}" class="rounded-lg bg-white p-5 shadow hover:ring-2 hover:ring-slate-300">
            <div class="text-sm text-gray-500">Mises bas attendues (7 jours)</div>
            <div class="mt-1 text-3xl font-bold">{{ $repro['mises_bas_attendues'] }}</div>
            <div class="text-xs text-gray-500">{{ $repro['gestantes'] }} femelle(s) gestante(s)</div>
        </a>

        <a href="{{ route('proprietaire.sante') }}" class="rounded-lg bg-white p-5 shadow hover:ring-2 hover:ring-slate-300">
            <div class="text-sm text-gray-500">Taux de mortalité ({{ $mort['jours'] }} jours)</div>
            <div class="mt-1 text-3xl font-bold {{ $mort['depasse'] ? 'text-red-600' : '' }}">
                {{ $mort['taux'] !== null ? number_format($mort['taux'], 1, ',', ' ').' %' : '-' }}
            </div>
            <div class="text-xs text-gray-500">{{ $mort['deces'] }} décès · seuil critique {{ $mort['seuil'] }} %</div>
        </a>

        <a href="{{ route('proprietaire.finances') }}" class="rounded-lg bg-white p-5 shadow hover:ring-2 hover:ring-slate-300">
            <div class="text-sm text-gray-500">Bénéfice net du mois</div>
            <div class="mt-1 text-2xl font-bold {{ $fin['benefice'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ Monnaie::format($fin['benefice']) }}</div>
            <div class="text-xs text-gray-500">CA {{ Monnaie::format($fin['ca']) }} − charges {{ Monnaie::format($fin['charges']) }}</div>
        </a>
    </div>

    {{-- Alertes et journée --}}
    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <section class="rounded-lg bg-white p-5 shadow lg:col-span-2">
            <h2 class="mb-3 font-semibold">
                Alertes et points d'attention
                <span class="rounded-full bg-gray-100 px-2 text-sm">{{ $alertes->count() }}</span>
            </h2>

            @forelse ($alertes as $alerte)
                <a href="{{ $alerte['lien'] }}" class="mb-2 block rounded border p-3 text-sm {{ $niveaux[$alerte['niveau']][0] }}">
                    <span class="mr-2 text-xs font-semibold uppercase">{{ $niveaux[$alerte['niveau']][1] }}</span>{{ $alerte['message'] }}
                </a>
            @empty
                <p class="text-sm text-emerald-700">Aucune alerte : tout est sous contrôle.</p>
            @endforelse
        </section>

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Aujourd'hui</h2>
            <div class="flex items-end justify-between">
                <div class="text-3xl font-bold">{{ $prog['pourcentage'] ?? 0 }} %</div>
                <div class="text-sm text-gray-500">{{ $prog['faites'] }} faite(s) sur {{ $prog['total'] }}</div>
            </div>
            <div class="mt-2 h-3 rounded bg-gray-200">
                <div class="h-3 rounded bg-emerald-500" style="width: {{ $prog['pourcentage'] ?? 0 }}%"></div>
            </div>
            <p class="mt-2 text-sm text-gray-600">
                {{ $d['journee']['cloturee'] ? 'Journée clôturée par le gestionnaire.' : $prog['restantes'].' tâche(s) restante(s).' }}
            </p>
            <a href="{{ route('proprietaire.supervision') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Supervision en temps réel →</a>
        </section>
    </div>

    @if ($notifications->isNotEmpty())
        <section class="mb-6 rounded-lg bg-white p-5 shadow">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">Notifications non lues</h2>
                <form method="POST" action="{{ route('proprietaire.notifications.lues') }}">
                    @csrf
                    <button class="text-sm text-blue-600 underline">Tout marquer comme lu</button>
                </form>
            </div>
            @foreach ($notifications as $notification)
                <p class="border-t py-1 text-sm">
                    {{ $notification->created_at->format('d/m/Y H:i') }} · {{ $notification->data['message'] ?? 'Notification' }}
                </p>
            @endforeach
        </section>
    @endif

    {{-- Résumé par module --}}
    <div class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Cheptel</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Femelles reproductrices</dt><dd class="font-medium">{{ $d['cheptel']['femelles'] }}</dd></div>
                <div class="flex justify-between"><dt>Mâles reproducteurs</dt><dd class="font-medium">{{ $d['cheptel']['males'] }}</dd></div>
                <div class="flex justify-between"><dt>Lots d'engraissement</dt><dd class="font-medium">{{ $d['cheptel']['lots'] }} ({{ $d['cheptel']['lapins_engraissement'] }} lapins)</dd></div>
                <div class="flex justify-between"><dt>En attente de validation</dt><dd class="font-medium">{{ $d['cheptel']['en_attente'] }}</dd></div>
                <div class="flex justify-between"><dt>Reproducteurs sans cage</dt><dd class="font-medium">{{ $d['cheptel']['sans_cage'] }}</dd></div>
            </dl>
            <a href="{{ route('proprietaire.cheptel') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Voir le cheptel →</a>
        </section>

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Reproduction</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Saillies en cours</dt><dd class="font-medium">{{ $repro['en_cours'] }}</dd></div>
                <div class="flex justify-between">
                    <dt>Naissances du mois</dt>
                    <dd class="font-medium">{{ $repro['naissances'] }}@if ($repro['objectif'] !== null) / {{ $repro['objectif'] }}@endif</dd>
                </div>
                <div class="flex justify-between">
                    <dt>Échec des saillies (90 j)</dt>
                    <dd class="font-medium {{ $repro['echec']['alerte'] ? 'text-red-600' : '' }}">
                        {{ $repro['echec']['taux'] !== null ? number_format($repro['echec']['taux'], 1, ',', ' ').' %' : '-' }}
                    </dd>
                </div>
            </dl>
            @if ($repro['pourcentage_objectif'] !== null)
                <div class="mt-2 h-2 rounded bg-gray-200">
                    <div class="h-2 rounded bg-emerald-500" style="width: {{ min(100, $repro['pourcentage_objectif']) }}%"></div>
                </div>
            @endif
            <a href="{{ route('proprietaire.reproduction') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Voir la reproduction →</a>
        </section>

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Santé</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Vaccinations à faire</dt><dd class="font-medium">{{ $d['sante']['vaccins_dus'] }}</dd></div>
                <div class="flex justify-between"><dt>Animaux ou lots bloqués à la vente</dt><dd class="font-medium">{{ $d['sante']['bloques'] }}</dd></div>
                <div class="flex justify-between"><dt>Produits en stock bas</dt><dd class="font-medium {{ $d['sante']['produits_bas']->isNotEmpty() ? 'text-red-600' : '' }}">{{ $d['sante']['produits_bas']->count() }}</dd></div>
            </dl>
            <a href="{{ route('proprietaire.sante') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Voir la santé →</a>
        </section>

        <section class="rounded-lg bg-white p-5 shadow">
            <h2 class="mb-3 font-semibold">Alimentation</h2>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt>Aliments suivis</dt><dd class="font-medium">{{ $d['alimentation']['aliments'] }}</dd></div>
                <div class="flex justify-between"><dt>En stock faible</dt><dd class="font-medium {{ $d['alimentation']['alertes']->isNotEmpty() ? 'text-red-600' : '' }}">{{ $d['alimentation']['alertes']->count() }}</dd></div>
                <div class="flex justify-between">
                    <dt>Autonomie la plus courte</dt>
                    <dd class="font-medium">{{ $d['alimentation']['autonomie_min'] !== null ? number_format($d['alimentation']['autonomie_min'], 1, ',', ' ').' j' : '-' }}</dd>
                </div>
            </dl>
            <a href="{{ route('proprietaire.aliments') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Voir les stocks →</a>
        </section>

        <section class="rounded-lg bg-white p-5 shadow xl:col-span-2">
            <h2 class="mb-3 font-semibold">Finances du mois</h2>
            <dl class="mb-3 grid gap-2 text-sm md:grid-cols-4">
                <div><dt class="text-gray-500">Chiffre d'affaires</dt><dd class="font-medium">{{ Monnaie::format($fin['ca']) }}</dd></div>
                <div><dt class="text-gray-500">Charges</dt><dd class="font-medium">{{ Monnaie::format($fin['charges']) }}</dd></div>
                <div><dt class="text-gray-500">Marge nette</dt><dd class="font-medium">{{ $fin['marge'] !== null ? number_format($fin['marge'], 1, ',', ' ').' %' : '-' }}</dd></div>
                <div><dt class="text-gray-500">Créances</dt><dd class="font-medium {{ $fin['creances'] > 0 ? 'text-red-600' : '' }}">{{ Monnaie::format($fin['creances']) }}</dd></div>
            </dl>

            @php $objectifMois = $fin['objectifs']['mois']; @endphp
            @if ($objectifMois['pourcentage'] !== null)
                <div class="mb-3">
                    <div class="flex justify-between text-xs text-gray-500">
                        <span>Objectif de ventes du mois</span><span>{{ $objectifMois['pourcentage'] }} %</span>
                    </div>
                    <div class="mt-1 h-2 rounded bg-gray-200">
                        <div class="h-2 rounded {{ $objectifMois['pourcentage'] >= 100 ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ min(100, $objectifMois['pourcentage']) }}%"></div>
                    </div>
                </div>
            @endif

            <canvas id="graphique-tableau" height="90"></canvas>
            <a href="{{ route('proprietaire.finances') }}" class="mt-3 inline-block text-sm text-blue-600 underline">Voir le tableau financier →</a>
        </section>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new Chart(document.getElementById('graphique-tableau'), {
                    type: 'bar',
                    data: {
                        labels: @json($fin['serie']->pluck('label')->values()),
                        datasets: [
                            { type: 'bar', label: "Chiffre d'affaires", data: @json($fin['serie']->pluck('ca')->values()) },
                            { type: 'bar', label: 'Charges', data: @json($fin['serie']->pluck('charges')->values()) },
                            { type: 'line', label: 'Bénéfice net', data: @json($fin['serie']->pluck('benefice')->values()), tension: 0.3 },
                        ],
                    },
                    options: { scales: { y: { beginAtZero: true } } },
                });
            });
        </script>
    @endpush
</x-layouts.proprietaire>