<?php

namespace App\Enums;

enum StatutReproducteur: string
{
    case Actif = 'actif';
    case Reforme = 'reforme';
    case Vendu = 'vendu';
    case Mort = 'mort';

    public function libelle(): string
    {
        return match ($this) {
            self::Actif => 'Actif',
            self::Reforme => 'Réformé',
            self::Vendu => 'Vendu',
            self::Mort => 'Mort',
        };
    }
}