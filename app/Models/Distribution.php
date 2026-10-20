<?php

namespace App\Models;

use App\Enums\CategorieAlimentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Distribution extends Model
{
    protected $table = 'distributions';

    protected $fillable = [
        'ration_id', 'aliment_id', 'categorie', 'date_distribution', 'nombre_animaux',
        'grammes_par_animal', 'quantite_theorique_kg', 'quantite_servie_kg', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieAlimentation::class,
            'date_distribution' => 'date',
            'quantite_theorique_kg' => 'decimal:2',
            'quantite_servie_kg' => 'decimal:2',
        ];
    }

    public function ration(): BelongsTo
    {
        return $this->belongsTo(Ration::class, 'ration_id');
    }

    public function aliment(): BelongsTo
    {
        return $this->belongsTo(Aliment::class, 'aliment_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** Écart entre la quantité servie et la quantité théorique, en pourcentage. */
    public function ecartPourcent(): ?float
    {
        $theorique = (float) $this->quantite_theorique_kg;

        return $theorique > 0
            ? round(((float) $this->quantite_servie_kg - $theorique) / $theorique * 100, 1)
            : null;
    }
}