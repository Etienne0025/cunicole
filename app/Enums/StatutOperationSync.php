<?php

namespace App\Enums;

enum StatutOperationSync: string
{
    case EnCours = 'en_cours';
    case Ok = 'ok';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Ok => 'Enregistrée',
            self::Refusee => 'Refusée',
        };
    }
}