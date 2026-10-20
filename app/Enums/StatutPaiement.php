<?php

namespace App\Enums;

enum StatutPaiement: string
{
    case Payee = 'payee';
    case Partielle = 'partielle';
    case Impayee = 'impayee';

    public function libelle(): string
    {
        return match ($this) {
            self::Payee => 'Payée',
            self::Partielle => 'Partiellement payée',
            self::Impayee => 'Impayée (crédit)',
        };
    }

    public function classes(): string
    {
        return match ($this) {
            self::Payee => 'bg-emerald-100 text-emerald-800',
            self::Partielle => 'bg-amber-100 text-amber-800',
            self::Impayee => 'bg-red-100 text-red-800',
        };
    }
}