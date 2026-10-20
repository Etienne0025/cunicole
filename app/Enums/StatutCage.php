<?php

namespace App\Enums;

enum StatutCage: string
{
    case Vide = 'vide';
    case Occupee = 'occupee';
    case ANettoyer = 'a_nettoyer';

    public function libelle(): string
    {
        return match ($this) {
            self::Vide => 'Vide',
            self::Occupee => 'Occupée',
            self::ANettoyer => 'À nettoyer',
        };
    }
}