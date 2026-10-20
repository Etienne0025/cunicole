<?php

namespace App\Support;

class Monnaie
{
    public static function decimales(): int
    {
        return (int) config('cunicole.devise.decimales', 0);
    }

    public static function arrondir(float|int|string|null $montant): float
    {
        return round((float) $montant, self::decimales());
    }

    /** Ex : « 23 750 FCFA » (espace insécable entre les milliers). */
    public static function format(float|int|string|null $montant, bool $avecSymbole = true): string
    {
        $texte = number_format((float) $montant, self::decimales(), ',', "\u{00A0}");

        return $avecSymbole ? $texte."\u{00A0}".config('cunicole.devise.symbole', 'FCFA') : $texte;
    }
}