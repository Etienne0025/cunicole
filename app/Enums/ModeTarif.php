<?php

namespace App\Enums;

enum ModeTarif: string
{
    case Kg = 'kg';
    case Unite = 'unite';

    public function libelle(): string
    {
        return match ($this) {
            self::Kg => 'Prix au kg (poids vif)',
            self::Unite => 'Prix à la pièce',
        };
    }

    public function suffixe(): string
    {
        return match ($this) {
            self::Kg => '/ kg',
            self::Unite => '/ pièce',
        };
    }
}