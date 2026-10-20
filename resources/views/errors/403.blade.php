@php
    // Les messages de refus écrits par l'application (en français) sont affichés ; les messages anglais par défaut sont remplacés
    $messageRefus = trim((string) ($exception?->getMessage() ?? ''));
    $messagesAnglais = ['', 'Forbidden', 'This action is unauthorized.'];
@endphp

@extends('errors.layout')

@section('code', '403')
@section('titre', 'Accès refusé')
@section('message', in_array($messageRefus, $messagesAnglais, true) ? "Vous n'avez pas l'autorisation d'ouvrir cette page." : $messageRefus)