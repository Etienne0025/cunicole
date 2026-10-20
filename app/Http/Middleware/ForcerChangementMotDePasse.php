<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcerChangementMotDePasse
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

       if ($utilisateur?->doit_changer_mot_de_passe && ! $request->routeIs('mot-de-passe.*', 'logout', 'hors-ligne', 'ping', 'manifest'))  {
            if ($request->expectsJson()) {
                abort(403, 'Vous devez d\'abord changer votre mot de passe.');
            }

            return redirect()->route('mot-de-passe.changer');
        }

        return $next($request);
    }
}