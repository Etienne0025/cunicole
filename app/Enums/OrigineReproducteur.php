<?php

namespace App\Enums;

enum OrigineReproducteur: string
{
    case Ferme = 'ferme';
    case Achat = 'achat';

    public function libelle(): string
    {
        return match ($this) {
            self::Ferme => 'Né à la ferme',
            self::Achat => 'Acheté',
        };
    }
}