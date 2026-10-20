@props(['saillie'])

@php
    $classes = match ($saillie->statut->value) {
        'en_attente' => 'bg-amber-100 text-amber-800',
        'gestante' => 'bg-blue-100 text-blue-800',
        'non_gestante' => 'bg-red-100 text-red-800',
        'mise_bas' => 'bg-purple-100 text-purple-800',
        'sevree' => 'bg-gray-200 text-gray-700',
        'interrompue' => 'bg-gray-200 text-gray-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>{{ $saillie->statut->libelle() }}</span>