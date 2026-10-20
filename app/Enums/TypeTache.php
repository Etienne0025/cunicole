<?php

namespace App\Enums;

enum TypeTache: string
{
    case Distribution = 'distribution';
    case SurveillanceMiseBas = 'surveillance_mise_bas';
    case Palpation = 'palpation';
    case BoiteNids = 'boite_nids';
    case Sevrage = 'sevrage';
    case Vaccination = 'vaccination';
    case Traitement = 'traitement';
    case NettoyageCage = 'nettoyage_cage';
    case PeseeLot = 'pesee_lot';
    case Quotidienne = 'quotidienne';

    public function libelle(): string
    {
        return match ($this) {
            self::Distribution => "Distribution d'aliment",
            self::SurveillanceMiseBas => 'Surveillance de mise bas',
            self::Palpation => 'Palpation',
            self::BoiteNids => 'Boîte à nids',
            self::Sevrage => 'Sevrage',
            self::Vaccination => 'Vaccination',
            self::Traitement => 'Traitement',
            self::NettoyageCage => 'Nettoyage de cage',
            self::PeseeLot => 'Pesée de lot',
            self::Quotidienne => 'Tâche quotidienne',
        };
    }

    /** Ces tâches se cochent à la main ; les autres se terminent quand l'action est enregistrée. */
    public function cochableManuellement(): bool
    {
        return in_array($this, [self::Quotidienne, self::SurveillanceMiseBas, self::Traitement], true);
    }

    /** Affiche un bouton « Fait » (la vaccination passe par un service qui décompte le stock). */
    public function boutonFait(): bool
    {
        return $this->cochableManuellement() || $this === self::Vaccination;
    }

    public function ordre(): int
    {
        return match ($this) {
            self::Distribution => 1,
            self::SurveillanceMiseBas => 2,
            self::Palpation => 3,
            self::BoiteNids => 4,
            self::Sevrage => 5,
            self::Vaccination => 6,
            self::Traitement => 7,
            self::NettoyageCage => 8,
            self::PeseeLot => 9,
            self::Quotidienne => 10,
        };
    }
}