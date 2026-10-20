<?php

namespace App\Enums;

enum CategorieAlimentation: string
{
    case Male = 'male';
    case FemelleVide = 'femelle_vide';
    case FemelleGestante = 'femelle_gestante';
    case FemelleAllaitante = 'femelle_allaitante';
    case Engraissement = 'engraissement';

    public function libelle(): string
    {
        return match ($this) {
            self::Male => 'Mâles reproducteurs',
            self::FemelleVide => 'Femelles au repos ou en attente de palpation',
            self::FemelleGestante => 'Femelles gestantes',
            self::FemelleAllaitante => 'Femelles allaitantes (avec leur portée)',
            self::Engraissement => 'Lapins en engraissement',
        };
    }

    public function parUnite(): string
    {
        return match ($this) {
            self::Male => 'par mâle',
            self::Engraissement => 'par lapin',
            default => 'par femelle',
        };
    }

    public function ordre(): int
    {
        return match ($this) {
            self::Male => 1,
            self::FemelleVide => 2,
            self::FemelleGestante => 3,
            self::FemelleAllaitante => 4,
            self::Engraissement => 5,
        };
    }
}