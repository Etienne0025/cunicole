<?php

namespace App\Enums;

enum TypeMouvementPharmacie: string
{
    case Achat = 'achat';
    case Utilisation = 'utilisation';
    case Ajustement = 'ajustement';

    public function libelle(): string
    {
        return match ($this) {
            self::Achat => 'Achat',
            self::Utilisation => 'Utilisation',
            self::Ajustement => 'Ajustement',
        };
    }
}