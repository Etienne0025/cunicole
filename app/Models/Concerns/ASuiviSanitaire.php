<?php

namespace App\Models\Concerns;

use App\Models\Traitement;
use App\Models\Vaccination;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait ASuiviSanitaire
{
    public function vaccinations(): MorphMany
    {
        return $this->morphMany(Vaccination::class, 'vaccinable');
    }

    public function traitements(): MorphMany
    {
        return $this->morphMany(Traitement::class, 'traitable');
    }

    /** Traitement dont le délai d'attente court encore (le plus long en premier). */
    public function traitementBloquant(): ?Traitement
    {
        $aujourdhui = today()->toDateString();

        return $this->traitements()
            ->where('date_debut', '<=', $aujourdhui)
            ->where('date_fin_attente', '>=', $aujourdhui)
            ->orderByDesc('date_fin_attente')
            ->first();
    }

    /** Servira au module Ventes (Phase 7) pour interdire la vente. */
    public function estBloquePourVente(): bool
    {
        return $this->traitementBloquant() !== null;
    }
}