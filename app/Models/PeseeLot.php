<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeseeLot extends Model
{
    protected $table = 'pesees_lots';

    protected $fillable = ['lot_id', 'date_pesee', 'poids_moyen_kg', 'nombre_pese', 'utilisateur_id'];

    protected function casts(): array
    {
        return [
            'date_pesee' => 'date',
            'poids_moyen_kg' => 'decimal:2',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotEngraissement::class, 'lot_id');
    }
}