<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CritereSelection extends Model
{
    protected $table = 'criteres_selection';

    protected $fillable = ['poids_min_kg', 'age_min_jours', 'prolificite_mere_min'];

    protected function casts(): array
    {
        return [
            'poids_min_kg' => 'decimal:2',
            'age_min_jours' => 'integer',
            'prolificite_mere_min' => 'decimal:1',
        ];
    }

    /** Les critères sont uniques pour la ferme : on les crée au premier accès. */
    public static function actuel(): self
    {
        return static::query()->firstOrCreate([], [
            'poids_min_kg' => 2.5,
            'age_min_jours' => 90,
            'prolificite_mere_min' => 6,
        ]);
    }
}