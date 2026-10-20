<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Gestionnaire' }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800 pb-20">

    <header class="sticky top-0 z-10 bg-emerald-700 text-white shadow">
        <div class="flex items-center justify-between px-4 py-3">
            <h1 class="text-lg font-semibold">{{ $title ?? 'Accueil' }}</h1>
            <div class="flex items-center gap-3 text-sm">
                <span id="statut-connexion" class="rounded-full bg-white/20 px-2 py-0.5">En ligne</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="underline">Quitter</button>
                </form>
            </div>
        </div>
    </header>

    <main class="p-4">
        @if (session('status'))
            <div class="mb-4 rounded bg-emerald-100 p-3 text-emerald-800">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>

    @php
        $navigation = [
            ['libelle' => 'Accueil', 'route' => 'gestionnaire.accueil', 'motif' => 'gestionnaire.accueil'],
            ['libelle' => 'Cages',   'route' => null, 'motif' => 'gestionnaire.cages.*'],
            ['libelle' => 'Élevage', 'route' => null, 'motif' => 'gestionnaire.elevage.*'],
            ['libelle' => 'Tâches',  'route' => null, 'motif' => 'gestionnaire.taches.*'],
            ['libelle' => 'Plus',    'route' => null, 'motif' => 'gestionnaire.plus.*'],
        ];
    @endphp

    <nav class="fixed bottom-0 inset-x-0 z-10 grid grid-cols-5 border-t bg-white text-xs">
        @foreach ($navigation as $element)
            <a href="{{ $element['route'] ? route($element['route']) : '#' }}"
               class="flex flex-col items-center py-3 {{ request()->routeIs($element['motif']) ? 'font-semibold text-emerald-700' : 'text-gray-500' }}">
                {{ $element['libelle'] }}
            </a>
        @endforeach
    </nav>
</body>
</html>