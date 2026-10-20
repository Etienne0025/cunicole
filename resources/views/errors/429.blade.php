@extends('errors.layout')

@section('code', '429')
@section('titre', 'Trop de tentatives')
@section('message', 'Trop de demandes ont été envoyées en peu de temps. Patientez quelques minutes avant de réessayer.')