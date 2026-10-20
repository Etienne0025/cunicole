@props(['cage'])

@php
    [$classes, $libelle] = match ($cage->etat()) {
        'inactive' => ['bg-gray-200 text-gray-700', 'Non activée'],
        'vide' => ['bg-emerald-100 text-emerald-800', 'Vide'],
        'occupee' => ['bg-blue-100 text-blue-800', 'Occupée'],
        'a_nettoyer' => ['bg-amber-100 text-amber-800', 'À nettoyer'],
    };
@endphp

<span {{ $attributes->merge(['class' => "rounded-full px-2 py-0.5 text-xs font-medium $classes"]) }}>{{ $libelle }}</span>