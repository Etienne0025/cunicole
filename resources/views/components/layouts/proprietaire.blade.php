<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Supervision' }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800" x-data="{ ouvert: false }">

    @php
        // 'motif' (facultatif) : liste d'adresses de routes pour laquelle l'entrée de menu reste surlignée
        $menu = [
            ['libelle' => 'Tableau de bord', 'route' => 'proprietaire.tableau_de_bord'],
            ['libelle' => 'Supervision du jour', 'route' => 'proprietaire.supervision'],
            ['libelle' => 'Rapports journaliers', 'route' => 'proprietaire.rapports.index', 'motif' => 'proprietaire.rapports.*'],
            ['libelle' => 'Saisies hors ligne refusées', 'route' => 'proprietaire.synchronisations'],
            ['libelle' => 'Cages et bâtiments', 'route' => 'proprietaire.cartographie'],
            ['libelle' => 'Cheptel', 'route' => 'proprietaire.cheptel'],
            ['libelle' => 'Reproduction', 'route' => 'proprietaire.reproduction', 'motif' => 'proprietaire.reproduction*'],
            ['libelle' => 'Sélection et renouvellement', 'route' => 'proprietaire.selection', 'motif' => 'proprietaire.selection*'],
            ['libelle' => 'Santé et mortalités', 'route' => 'proprietaire.sante', 'motif' => 'proprietaire.sante*'],
            ['libelle' => 'Pharmacie', 'route' => 'proprietaire.pharmacie', 'motif' => 'proprietaire.pharmacie*'],
            ['libelle' => 'Calendrier prophylactique', 'route' => 'proprietaire.protocoles.index', 'motif' => 'proprietaire.protocoles.*'],
            ['libelle' => 'Aliments et stocks', 'route' => 'proprietaire.aliments', 'motif' => 'proprietaire.aliments*'],
            ['libelle' => 'Rations', 'route' => 'proprietaire.rations.index', 'motif' => 'proprietaire.rations.*'],
            ['libelle' => 'Consommation', 'route' => 'proprietaire.consommation'],
            ['libelle' => 'Tableau financier', 'route' => 'proprietaire.finances'],
            ['libelle' => 'Ventes et créances', 'route' => 'proprietaire.ventes.index', 'motif' => 'proprietaire.ventes.*'],
            ['libelle' => 'Charges', 'route' => 'proprietaire.charges.index', 'motif' => 'proprietaire.charges.*'],
            ['libelle' => 'Tarifs et conditions de vente', 'route' => 'proprietaire.tarifs', 'motif' => 'proprietaire.tarifs*'],
            ['libelle' => 'Comptes utilisateurs', 'route' => 'proprietaire.utilisateurs.index', 'motif' => 'proprietaire.utilisateurs.*'],
        ];

        $alerteMortalite = app(\App\Services\SuiviMortalite::class)->alerte();
        $alertesStock = app(\App\Services\GestionAliments::class)->alertes();
    @endphp

    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-20 w-64 overflow-y-auto bg-slate-900 text-slate-200 transition md:static md:translate-x-0"
               :class="ouvert ? 'translate-x-0' : '-translate-x-full'">
            <div class="px-6 py-5 text-xl font-bold text-white">{{ config('app.name') }}</div>
            <nav class="space-y-1 px-3 pb-6">
                @foreach ($menu as $element)
                    @php $actif = request()->routeIs($element['motif'] ?? $element['route']); @endphp
                    <a href="{{ route($element['route']) }}"
                       class="block rounded px-3 py-2 text-sm {{ $actif ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                        {{ $element['libelle'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex items-center justify-between bg-white px-4 py-3 shadow">
                <div class="flex items-center gap-3">
                    <button type="button" class="md:hidden" @click="ouvert = !ouvert" aria-label="Ouvrir le menu">☰</button>
                    <h1 class="text-lg font-semibold">{{ $title ?? 'Supervision' }}</h1>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('profile.edit') }}" class="underline">{{ auth()->user()->nom_complet }} ({{ auth()->user()->role->libelle() }})</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-red-600 underline">Déconnexion</button>
                    </form>
                </div>
            </header>

            <main class="p-6">
                @if (session('status'))
                    <div class="mb-4 rounded bg-emerald-100 p-3 text-emerald-800">{{ session('status') }}</div>
                @endif

                @if ($errors->has('action'))
                    <div class="mb-4 rounded bg-red-100 p-3 text-red-800">{{ $errors->first('action') }}</div>
                @endif

                @if ($alerteMortalite)
                    <a href="{{ route('proprietaire.sante') }}" class="mb-4 block rounded border border-red-400 bg-red-100 p-3 text-red-800">
                        <strong>URGENT :</strong> le taux de mortalité est de {{ $alerteMortalite['taux'] }} % sur {{ $alerteMortalite['jours'] }} jours
                        (seuil critique : {{ $alerteMortalite['seuil'] }} %, {{ $alerteMortalite['deces'] }} décès). Cliquez pour voir le détail.
                    </a>
                @endif

                @if ($alertesStock->isNotEmpty())
                    <a href="{{ route('proprietaire.aliments') }}" class="mb-4 block rounded border border-amber-400 bg-amber-100 p-3 text-amber-900">
                        <strong>Stock faible :</strong>
                        {{ $alertesStock->map(fn ($a) => $a['aliment']->nom.' ('.($a['autonomie'] <= 0 ? 'épuisé' : number_format($a['autonomie'], 1, ',', ' ').' j').')')->join(', ') }}.
                        Pensez à réapprovisionner.
                    </a>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>