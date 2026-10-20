<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Portee extends Model
{
    protected $table = 'portees';

    protected $fillable = [
        'saillie_id', 'date_mise_bas', 'nes_vivants', 'morts_nes',
        'date_sevrage', 'sevres','morts_avant_sevrage', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_mise_bas' => 'date',
            'date_sevrage' => 'date',
        ];
    }

    public function saillie(): BelongsTo
    {
        return $this->belongsTo(Saillie::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(LotEngraissement::class);
    }

    public function taille(): int
    {
        return $this->nes_vivants + $this->morts_nes;
    }

    /** Lapereaux vivants avant sevrage (nés vivants moins ceux morts depuis). */
public function vivantsActuels(): int
{
    return max(0, $this->nes_vivants - $this->morts_avant_sevrage);
}
}