<?php

namespace App\Enums;

enum Role: string
{
    case Proprietaire = 'proprietaire';
    case Gestionnaire = 'gestionnaire';

    public function libelle(): string
    {
        return match ($this) {
            self::Proprietaire => 'Propriétaire',
            self::Gestionnaire => 'Gestionnaire',
        };
    }

    public function routeAccueil(): string
    {
        return match ($this) {
            self::Proprietaire => 'proprietaire.tableau_de_bord',
            self::Gestionnaire => 'gestionnaire.accueil',
        };
    }
}
