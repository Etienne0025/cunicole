<?php

namespace App\Enums;

enum TypeCage: string
{
    case Male = 'male';
    case Femelle = 'femelle';
    case Maternite = 'maternite';
    case Engraissement = 'engraissement';

    public function libelle(): string
    {
        return match ($this) {
            self::Male => 'Mâle reproducteur',
            self::Femelle => 'Femelle reproductrice',
            self::Maternite => 'Maternité',
            self::Engraissement => 'Engraissement',
        };
    }

    /** @return array<int, self> */
public static function accepteursDe(SexeAnimal $sexe): array
{
    return match ($sexe) {
        SexeAnimal::Male => [self::Male],
        SexeAnimal::Femelle => [self::Femelle, self::Maternite],
    };
}
}