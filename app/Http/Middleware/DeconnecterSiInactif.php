<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DeconnecterSiInactif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur !== null && ! $utilisateur->actif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Compte désactivé.');
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Ce compte est désactivé. Contactez le propriétaire de la ferme.']);
        }

        return $next($request);
    }
}