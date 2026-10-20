<?php

namespace App\Enums;

enum StatutSaillie: string
{
    case EnAttente = 'en_attente';
    case Gestante = 'gestante';
    case NonGestante = 'non_gestante';
    case MiseBas = 'mise_bas';
    case Sevree = 'sevree';
    case Interrompue = 'interrompue';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de palpation',
            self::Gestante => 'Gestante',
            self::NonGestante => 'Non gestante',
            self::MiseBas => 'Mise bas faite',
            self::Sevree => 'Sevrée',
            self::Interrompue => 'Interrompue (femelle sortie ou décédée)',
        };
    }

    /** @return array<int, string> statuts d'une saillie encore en cours */
    public static function valeursEnCours(): array
    {
        return [self::EnAttente->value, self::Gestante->value, self::MiseBas->value];
    }
}