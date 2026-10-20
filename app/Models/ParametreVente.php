<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreVente extends Model
{
    protected $table = 'parametres_ventes';

    protected $fillable = ['remise_max_pct', 'credit_autorise', 'objectif_hebdomadaire', 'objectif_mensuel'];

    protected function casts(): array
    {
        return [
            'remise_max_pct' => 'decimal:2',
            'credit_autorise' => 'boolean',
            'objectif_hebdomadaire' => 'decimal:2',
            'objectif_mensuel' => 'decimal:2',
        ];
    }

    /** Les conditions de vente sont uniques pour la ferme : créées au premier accès. */
    public static function actuel(): self
    {
        return static::query()->firstOrCreate([], [
            'remise_max_pct' => 0,
            'credit_autorise' => false,
        ]);
    }
}