<?php

namespace App\Enums;

enum StatutLot: string
{
    case EnCours = 'en_cours';
    case Termine = 'termine';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
        };
    }
}