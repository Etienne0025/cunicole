<?php

namespace App\Enums;

enum SexeAnimal: string
{
    case Male = 'male';
    case Femelle = 'femelle';

    public function libelle(): string
    {
        return match ($this) {
            self::Male => 'Mâle',
            self::Femelle => 'Femelle',
        };
    }
}