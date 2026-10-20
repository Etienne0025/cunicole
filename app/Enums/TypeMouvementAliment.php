<?php

namespace App\Enums;

enum TypeMouvementAliment: string
{
    case Reception = 'reception';
    case Distribution = 'distribution';
    case Ajustement = 'ajustement';

    public function libelle(): string
    {
        return match ($this) {
            self::Reception => 'Réception',
            self::Distribution => 'Distribution',
            self::Ajustement => 'Ajustement',
        };
    }
}