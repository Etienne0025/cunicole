@props(['vente'])

@php $statut = $vente->statutPaiement(); @endphp

<span {{ $attributes->merge(['class' => 'rounded-full px-2 py-0.5 text-xs font-medium '.$statut->classes()]) }}>{{ $statut->libelle() }}</span>