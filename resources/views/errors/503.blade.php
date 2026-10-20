@extends('errors.layout')

@section('tete')
    <meta http-equiv="refresh" content="30">
@endsection

@section('code', '503')
@section('titre', 'Mise à jour en cours')
@section('message', "L'application est en cours de mise à jour. Elle sera de nouveau disponible dans quelques instants (cette page se recharge automatiquement).")