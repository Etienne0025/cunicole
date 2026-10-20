<?php

namespace App\Http\Middleware;

use App\Enums\StatutOperationSync;
use App\Models\OperationSync;
use App\Support\HorsLigne;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Traite les saisies envoyées avec une clé « X-Cle-Operation » (mode hors ligne) :
 * une même clé n'est jamais appliquée deux fois, et la réponse est toujours en JSON.
 * Sans cette clé, la requête se déroule normalement.
 */
class OperationHorsLigne
{
    public function handle(Request $request, Closure $next): Response
    {
        $cle = $request->header('X-Cle-Operation');

        if ($cle === null) {
            return $next($request);
        }

        $nomRoute = (string) $request->route()?->getName();

        if (! Str::isUuid($cle)) {
            return $this->refus('Identifiant de saisie invalide.');
        }
        if (! $request->isMethod('POST') || ! array_key_exists($nomRoute, HorsLigne::routes())) {
            return $this->refus("Cette action n'est pas disponible hors ligne : faites-la quand vous avez du réseau.");
        }

        $moment = $this->momentDeLaSaisie($request);

        if ($moment === null) {
            $jours = (int) config('cunicole.hors_ligne.age_max_jours', 14);

            return $this->refus("Saisie trop ancienne (plus de {$jours} jours) : elle n'a pas été enregistrée. Ressaisissez-la si elle est encore utile.");
        }

        $existante = OperationSync::where('cle', $cle)->first();

        // Une saisie « en cours » depuis plus de 5 minutes est considérée comme interrompue
        if ($existante?->statut === StatutOperationSync::EnCours && $existante->created_at->lt(now()->subMinutes(5))) {
            $existante->delete();
            $existante = null;
        }

        if ($existante !== null) {
            return $this->reponseExistante($existante, (int) $request->user()->id);
        }

        try {
            $operation = OperationSync::create([
                'cle' => $cle,
                'utilisateur_id' => $request->user()->id,
                'route' => $nomRoute,
                'statut' => StatutOperationSync::EnCours,
                'effectue_le' => $moment,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['statut' => 'en_cours', 'message' => 'Cette saisie est déjà en cours de traitement.'], 409);
        }

        // Les erreurs doivent revenir en JSON (et non en redirection) pour que le téléphone les comprenne
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('effectue_le', $moment);
        app()->instance('moment.operation', $moment);

        try {
            $reponse = $next($request);
        } catch (Throwable $erreur) {
            $operation->delete();

            throw $erreur;
        } finally {
            app()->forgetInstance('moment.operation');
        }

        return $this->conclure($request, $operation, $reponse);
    }

    private function conclure(Request $request, OperationSync $operation, Response $reponse): Response
    {
        $statut = $reponse->getStatusCode();

        if ($statut >= 200 && $statut < 400) {
            $message = $request->hasSession() ? $request->session()->pull('status') : null;

            $operation->update(['statut' => StatutOperationSync::Ok, 'traitee_le' => now()]);

            return response()->json([
                'statut' => 'ok',
                'message' => $message,
                'redirection' => $reponse instanceof RedirectResponse ? $reponse->getTargetUrl() : null,
            ]);
        }

        if (in_array($statut, [404, 405, 422], true)) {
            $message = $this->messageDe($reponse, $statut);

            $operation->update([
                'statut' => StatutOperationSync::Refusee,
                'message' => Str::limit($message, 500, ''),
                'traitee_le' => now(),
            ]);

            return $this->refus($message);
        }

        // Accès refusé, panne du serveur... : aucune trace, la saisie pourra être renvoyée
        $operation->delete();

        return $reponse;
    }

    private function reponseExistante(OperationSync $operation, int $utilisateurId): JsonResponse
    {
        if ((int) $operation->utilisateur_id !== $utilisateurId) {
            return $this->refus('Identifiant de saisie déjà utilisé.');
        }

        return match ($operation->statut) {
            StatutOperationSync::Ok => response()->json(['statut' => 'deja', 'message' => 'Cette saisie était déjà enregistrée.']),
            StatutOperationSync::Refusee => $this->refus($operation->message ?? 'Saisie refusée par le serveur.'),
            StatutOperationSync::EnCours => response()->json(['statut' => 'en_cours', 'message' => 'Cette saisie est déjà en cours de traitement.'], 409),
        };
    }

    /** Heure de la saisie fournie par le téléphone, bornée : jamais dans le futur, jamais trop ancienne. */
    private function momentDeLaSaisie(Request $request): ?CarbonInterface
    {
        $maintenant = now();
        $brut = $request->header('X-Effectue-Le');

        if ($brut === null) {
            return $maintenant;
        }

        try {
            $moment = Carbon::parse($brut)->timezone(config('app.timezone'));
        } catch (Throwable) {
            return $maintenant;
        }

        if ($moment->gt($maintenant)) {
            return $maintenant;
        }
        if ($moment->lt($maintenant->copy()->subDays((int) config('cunicole.hors_ligne.age_max_jours', 14)))) {
            return null;
        }

        return $moment;
    }

    private function messageDe(Response $reponse, int $statut): string
    {
        if ($statut === 404) {
            return 'Élément introuvable : il a peut-être été supprimé depuis la saisie.';
        }

        $json = json_decode((string) $reponse->getContent(), true);

        if (is_array($json)) {
            $erreurs = collect($json['errors'] ?? [])->flatten()->unique()->implode(' ');

            if ($erreurs !== '') {
                return $erreurs;
            }
            if (! empty($json['message'])) {
                return (string) $json['message'];
            }
        }

        return 'Saisie refusée par le serveur.';
    }

    private function refus(string $message): JsonResponse
    {
        return response()->json(['statut' => 'refusee', 'message' => $message], 422);
    }
}