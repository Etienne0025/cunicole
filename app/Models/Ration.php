<?php

namespace App\Models;

use App\Enums\CategorieAlimentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ration extends Model
{
    protected $table = 'rations';

    protected $fillable = ['categorie', 'aliment_id', 'grammes_par_jour', 'actif', 'notes'];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieAlimentation::class,
            'grammes_par_jour' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function aliment(): BelongsTo
    {
        return $this->belongsTo(Aliment::class, 'aliment_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class, 'ration_id');
    }

    /** Quantité théorique, en kg, pour un nombre d'animaux donné. */
    public function quantiteTheorique(int $nombre): float
    {
        return round($nombre * $this->grammes_par_jour / 1000, 2);
    }
}