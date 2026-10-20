<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HorsLigne
{
    /** @return array<string, string> nom de route => libellé */
    public static function routes(): array
    {
        return config('cunicole.hors_ligne.routes', []);
    }

    public static function libelle(?string $nomRoute): string
    {
        return self::routes()[$nomRoute] ?? ($nomRoute ?? 'Saisie');
    }

    /**
     * Motifs d'adresses lus par le navigateur : « gestionnaire/taches/ * /cocher » (l'étoile remplace un paramètre).
     *
     * @return array<int, array{motif: string, libelle: string}>
     */
    public static function motifs(): array
    {
        $motifs = [];

        foreach (self::routes() as $nom => $libelle) {
            $route = Route::getRoutes()->getByName($nom);

            if ($route !== null) {
                $motifs[] = ['motif' => preg_replace('/\{[^}]+\}/', '*', $route->uri()), 'libelle' => $libelle];
            }
        }

        return $motifs;
    }

    /** @return array<int, string> adresses relatives des pages à préparer sur le téléphone */
    public static function pages(): array
    {
        return collect(config('cunicole.hors_ligne.pages', []))
            ->filter(fn (string $nom) => Route::has($nom))
            ->map(fn (string $nom) => route($nom, [], false))
            ->values()
            ->all();
    }

    /** Heure réelle de la saisie : celle du téléphone si elle a été faite hors ligne, sinon maintenant. */
    public static function moment(Request $request): CarbonInterface
    {
        return $request->attributes->get('effectue_le') ?? now();
    }
}