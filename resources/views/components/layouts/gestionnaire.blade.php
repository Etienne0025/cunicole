<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Gestionnaire' }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Application installable et mode hors ligne --}}
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icone-192.png">
    <meta name="theme-color" content="#047857">
    <meta name="utilisateur-id" content="{{ auth()->id() }}">
    <meta name="url-synchronisation" content="{{ route('gestionnaire.synchronisation.envoyer') }}">
    <meta name="url-etat-synchronisation" content="{{ route('gestionnaire.synchronisation.etat') }}">
</head>
<body class="bg-gray-100 text-gray-800 pb-20" data-espace="gestionnaire">

    <header class="sticky top-0 z-10 bg-emerald-700 text-white shadow">
        <div class="flex items-center justify-between px-4 py-3">
            <h1 class="text-lg font-semibold">{{ $title ?? 'Accueil' }}</h1>
            <div class="flex items-center gap-3 text-sm">
                <a id="compteur-sync" href="{{ route('gestionnaire.synchronisation.index') }}" class="hidden"></a>
                <span id="statut-connexion" class="rounded-full bg-white/20 px-2 py-0.5">En ligne</span>
                <a href="{{ route('profile.edit') }}" class="underline">Mon compte</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="underline">Quitter</button>
                </form>
            </div>
        </div>
    </header>

    <main class="p-4">
        <div id="bandeau-sync" class="hidden" role="status"></div>

        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 p-3 text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($errors->has('action'))
            <div class="mb-4 rounded bg-red-100 p-3 text-red-800">{{ $errors->first('action') }}</div>
        @endif

        {{ $slot }}
    </main>

    @php
        // 'motif' : une ou plusieurs adresses de routes pour lesquelles le bouton reste surligné
        $navigation = [
            ['libelle' => 'Accueil', 'route' => 'gestionnaire.accueil', 'motif' => 'gestionnaire.accueil'],
            ['libelle' => 'Cages', 'route' => 'gestionnaire.cages.index', 'motif' => 'gestionnaire.cages.*'],
            ['libelle' => 'Élevage', 'route' => 'gestionnaire.elevage', 'motif' => ['gestionnaire.elevage', 'gestionnaire.reproducteurs.*', 'gestionnaire.saillies.*', 'gestionnaire.lots.*']],
            ['libelle' => 'Tâches', 'route' => 'gestionnaire.taches.index', 'motif' => 'gestionnaire.taches.*'],
            ['libelle' => 'Plus', 'route' => 'gestionnaire.plus', 'motif' => ['gestionnaire.plus', 'gestionnaire.batiments.*', 'gestionnaire.sante.*', 'gestionnaire.alimentation.*', 'gestionnaire.ventes.*', 'gestionnaire.depenses.*', 'gestionnaire.synchronisation.*']],
        ];
    @endphp

    <nav class="fixed bottom-0 inset-x-0 z-10 grid grid-cols-5 border-t bg-white text-xs">
        @foreach ($navigation as $element)
            <a href="{{ route($element['route']) }}"
               class="flex flex-col items-center py-3 {{ request()->routeIs(...(array) $element['motif']) ? 'font-semibold text-emerald-700' : 'text-gray-500' }}">
                {{ $element['libelle'] }}
            </a>
        @endforeach
    </nav>

    @stack('scripts')
</body>
</html>