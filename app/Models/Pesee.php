<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pesee extends Model
{
    protected $table = 'pesees';

    protected $fillable = ['reproducteur_id', 'date_pesee', 'poids_kg', 'utilisateur_id'];

    protected function casts(): array
    {
        return [
            'date_pesee' => 'date',
            'poids_kg' => 'decimal:2',
        ];
    }

    public function reproducteur(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class);
    }
}