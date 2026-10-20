<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Supervision' }} - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-800" x-data="{ open: false }">

    @php
        $menu = [
            ['label' => 'Tableau de bord', 'route' => 'owner.dashboard'],
            ['label' => 'Cages et bâtiments', 'route' => null],
            ['label' => 'Reproduction', 'route' => null],
            ['label' => 'Cheptel', 'route' => null],
            ['label' => 'Santé', 'route' => null],
            ['label' => 'Alimentation et stocks', 'route' => null],
            ['label' => 'Finances', 'route' => null],
            ['label' => 'Tâches et utilisateurs', 'route' => null],
        ];
    @endphp

    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-20 w-64 -translate-x-full bg-slate-900 text-slate-200 transition md:static md:translate-x-0"
               :class="{ 'translate-x-0': open }">
            <div class="px-6 py-5 text-xl font-bold text-white">{{ config('app.name') }}</div>
            <nav class="space-y-1 px-3">
                @foreach ($menu as $item)
                    <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                       class="block rounded px-3 py-2 text-sm {{ $item['route'] && request()->routeIs($item['route']) ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1">
            <header class="flex items-center justify-between bg-white px-4 py-3 shadow">
                <div class="flex items-center gap-3">
                    <button class="md:hidden" @click="open = !open">☰</button>
                    <h1 class="text-lg font-semibold">{{ $title ?? 'Supervision' }}</h1>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span>{{ auth()->user()->name }} ({{ auth()->user()->role->label() }})</span>
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
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>