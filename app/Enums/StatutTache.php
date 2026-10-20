<?php

namespace App\Enums;

enum StatutTache: string
{
    case AFaire = 'a_faire';
    case Faite = 'faite';
    case NonFaite = 'non_faite';
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::AFaire => 'À faire',
            self::Faite => 'Faite',
            self::NonFaite => 'Non faite',
            self::Annulee => 'Annulée',
        };
    }

    /** Ordre d'affichage : à faire, non faites, puis faites. */
    public function ordre(): int
    {
        return match ($this) {
            self::AFaire => 0,
            self::NonFaite => 1,
            self::Faite => 2,
            self::Annulee => 3,
        };
    }
}