<?php

use App\Http\Middleware\DeconnecterSiInactif;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\ForcerChangementMotDePasse;
use App\Http\Middleware\VerifierRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => VerifierRole::class,
        ]);

        // Un utilisateur déjà connecté qui ouvre « / » ou « /login » est envoyé vers son espace
        $middleware->redirectUsersTo(fn (Request $request) => route('accueil'));

        // L'ordre compte : les en-têtes de sécurité enveloppent tous les autres contrôles
        $middleware->web(append: [
            EnTetesSecurite::class,
            DeconnecterSiInactif::class,
            ForcerChangementMotDePasse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();