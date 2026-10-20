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
                <span id="connection-status" class="rounded-full bg-white/20 px-2 py-0.5">En ligne</span>
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
        $nav = [
            ['label' => 'Accueil', 'route' => 'manager.dashboard', 'pattern' => 'manager.dashboard'],
            ['label' => 'Cages',   'route' => null, 'pattern' => 'manager.cages.*'],
            ['label' => 'Élevage', 'route' => null, 'pattern' => 'manager.breeding.*'],
            ['label' => 'Tâches',  'route' => null, 'pattern' => 'manager.tasks.*'],
            ['label' => 'Plus',    'route' => null, 'pattern' => 'manager.more.*'],
        ];
    @endphp

    <nav class="fixed bottom-0 inset-x-0 z-10 grid grid-cols-5 border-t bg-white text-xs">
        @foreach ($nav as $item)
            <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
               class="flex flex-col items-center py-3 {{ request()->routeIs($item['pattern']) ? 'font-semibold text-emerald-700' : 'text-gray-500' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</body>
</html>