<?php

namespace App\Enums;

/** Les valeurs sont identiques aux noms courts du « morph map » (reproducteur, lot). */
enum CibleVaccination: string
{
    case Reproducteur = 'reproducteur';
    case Lot = 'lot';

    public function libelle(): string
    {
        return match ($this) {
            self::Reproducteur => 'Reproducteurs',
            self::Lot => "Lots d'engraissement",
        };
    }
}