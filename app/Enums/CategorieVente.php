<?php

namespace App\Enums;

enum CategorieVente: string
{
    case Engraissement = 'engraissement';
    case Reproducteur = 'reproducteur';
    case Reforme = 'reforme';

    public function libelle(): string
    {
        return match ($this) {
            self::Engraissement => "Lapins d'engraissement (lots)",
            self::Reproducteur => 'Reproducteurs en bon état',
            self::Reforme => 'Animaux réformés',
        };
    }

    public function ordre(): int
    {
        return match ($this) {
            self::Engraissement => 1,
            self::Reproducteur => 2,
            self::Reforme => 3,
        };
    }
}