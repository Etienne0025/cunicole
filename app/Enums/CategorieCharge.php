<?php

namespace App\Enums;

enum CategorieCharge: string
{
    // Dépenses courantes (saisies surtout par le gestionnaire)
    case Litiere = 'litiere';
    case Hygiene = 'hygiene';
    case PetitMateriel = 'petit_materiel';
    case Transport = 'transport';
    case AutreCourante = 'autre_courante';

    // Charges fixes (saisies par le propriétaire)
    case Salaires = 'salaires';
    case Loyer = 'loyer';
    case Energie = 'energie';
    case Entretien = 'entretien';
    case AutreFixe = 'autre_fixe';

    public function libelle(): string
    {
        return match ($this) {
            self::Litiere => 'Paille et litière',
            self::Hygiene => 'Désinfectant et hygiène',
            self::PetitMateriel => 'Petit matériel',
            self::Transport => 'Transport',
            self::AutreCourante => 'Autre dépense courante',
            self::Salaires => 'Salaires',
            self::Loyer => 'Loyer',
            self::Energie => 'Électricité et eau',
            self::Entretien => 'Entretien et réparations',
            self::AutreFixe => 'Autre charge fixe',
        };
    }

    public function estFixe(): bool
    {
        return in_array($this, [self::Salaires, self::Loyer, self::Energie, self::Entretien, self::AutreFixe], true);
    }

    /** @return array<int, self> */
    public static function courantes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => ! $c->estFixe()));
    }

    /** @return array<int, self> */
    public static function fixes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c->estFixe()));
    }

    /** @return array<int, string> */
    public static function valeursCourantes(): array
    {
        return array_map(fn (self $c) => $c->value, self::courantes());
    }

    /** @return array<int, string> */
    public static function valeursFixes(): array
    {
        return array_map(fn (self $c) => $c->value, self::fixes());
    }
}