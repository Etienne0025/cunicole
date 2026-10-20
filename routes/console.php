<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Checklist du jour, générée chaque nuit
Schedule::command('taches:generer')->dailyAt('00:05')->withoutOverlapping();

// Alertes de stock d'aliments (autonomie inférieure au seuil)
Schedule::command('aliments:verifier-stocks')->dailyAt('06:00')->withoutOverlapping();

// Purge du journal des saisies hors ligne (conservé 90 jours)
Schedule::command('model:prune')->daily();

// Signal de vie : permet à « cunicole:verifier-production » de savoir que le planificateur tourne
Schedule::call(fn () => Cache::put('planificateur_battement', now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('battement-planificateur');