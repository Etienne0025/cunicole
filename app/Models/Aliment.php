<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aliment extends Model
{
    protected $table = 'aliments';

    protected $fillable = ['nom', 'poids_sac_kg', 'stock_kg', 'actif', 'notes'];

    protected function casts(): array
    {
        return [
            'poids_sac_kg' => 'decimal:2',
            'stock_kg' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementAliment::class, 'aliment_id');
    }

    public function rations(): HasMany
    {
        return $this->hasMany(Ration::class, 'aliment_id');
    }
}