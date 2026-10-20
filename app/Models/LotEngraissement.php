<?php

namespace App\Models;

use App\Enums\StatutLot;
use App\Models\Concerns\ASuiviSanitaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LotEngraissement extends Model
{
    use ASuiviSanitaire;

    protected $table = 'lots_engraissement';

    protected $fillable = [
        'code', 'portee_id', 'cage_id', 'effectif_initial', 'effectif_actuel', 'date_debut', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutLot::class,
            'date_debut' => 'date',
        ];
    }

    public function portee(): BelongsTo
    {
        return $this->belongsTo(Portee::class);
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function pesees(): HasMany
    {
        return $this->hasMany(PeseeLot::class, 'lot_id');
    }

    /** Pesée la plus récente : date de pesée la plus tardive, puis identifiant le plus élevé. */
    public function dernierePesee(): HasOne
    {
        return $this->hasOne(PeseeLot::class, 'lot_id')->latestOfMany(['date_pesee', 'id']);
    }

    public function reproducteurs(): HasMany
    {
        return $this->hasMany(Reproducteur::class, 'lot_origine_id');
    }

    public function ageEnJours(): ?int
    {
        return $this->portee ? (int) $this->portee->date_mise_bas->diffInDays(today()) : null;
    }
}