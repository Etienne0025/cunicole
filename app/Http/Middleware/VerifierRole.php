<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $utilisateur = $request->user();

        abort_unless(
            $utilisateur && $utilisateur->actif && in_array($utilisateur->role->value, $roles, true),
            403,
            "Vous n'avez pas accès à cet espace."
        );

        return $next($request);
    }
}
