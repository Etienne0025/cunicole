<?php

namespace App\Models;

use App\Enums\StatutCage;
use App\Enums\TypeCage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cage extends Model
{
    use HasFactory;

    protected $table = 'cages';

    protected $fillable = [
        'batiment_id', 'code', 'type', 'capacite', 'statut', 'activee_le', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeCage::class,
            'statut' => StatutCage::class,
            'capacite' => 'integer',
            'activee_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Cage $cage) {
            $cage->uuid ??= (string) Str::uuid();
        });
    }

    // Les URLs utilisent l'uuid et non l'id
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function batiment(): BelongsTo
    {
        return $this->belongsTo(Batiment::class);
    }

    public function estActivee(): bool
    {
        return $this->activee_le !== null;
    }

    /** État à afficher : inactive, vide, occupee ou a_nettoyer */
    public function etat(): string
    {
        return $this->estActivee() ? $this->statut->value : 'inactive';
    }
public function reproducteurs(): HasMany
{
    return $this->hasMany(Reproducteur::class);
}

public function lots(): HasMany
{
    return $this->hasMany(LotEngraissement::class);
}


}