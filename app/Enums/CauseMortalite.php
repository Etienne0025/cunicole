<?php

namespace App\Enums;

enum CauseMortalite: string
{
    case Inconnue = 'inconnue';
    case Maladie = 'maladie';
    case Digestive = 'digestive';
    case Respiratoire = 'respiratoire';
    case Blessure = 'blessure';
    case Chaleur = 'chaleur';
    case MiseBas = 'mise_bas';
    case NidLait = 'nid_lait';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Inconnue => 'Cause inconnue',
            self::Maladie => 'Maladie suspectée',
            self::Digestive => 'Problème digestif',
            self::Respiratoire => 'Problème respiratoire',
            self::Blessure => 'Blessure ou accident',
            self::Chaleur => 'Chaleur ou stress',
            self::MiseBas => 'Mise bas difficile',
            self::NidLait => 'Froid, nid ou manque de lait (lapereaux)',
            self::Autre => 'Autre (précisez)',
        };
    }
}