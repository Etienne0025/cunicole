<?php

namespace App\Enums;

enum CibleMortalite: string
{
    case Reproducteur = 'reproducteur';
    case Lot = 'lot';
    case Portee = 'portee';

    public function libelle(): string
    {
        return match ($this) {
            self::Reproducteur => 'Reproducteur',
            self::Lot => "Lot d'engraissement",
            self::Portee => 'Portée (avant sevrage)',
        };
    }
}