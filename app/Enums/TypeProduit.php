<?php

namespace App\Enums;

enum TypeProduit: string
{
    case Vaccin = 'vaccin';
    case Traitement = 'traitement';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Vaccin => 'Vaccin',
            self::Traitement => 'Traitement',
            self::Autre => 'Autre (désinfectant, complément...)',
        };
    }
}