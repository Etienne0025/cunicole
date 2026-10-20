<?php

namespace App\Models;

use App\Enums\TypeMouvementAliment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementAliment extends Model
{
    protected $table = 'mouvements_aliments';

    protected $fillable = [
        'aliment_id', 'type', 'quantite_kg', 'nombre_sacs', 'poids_sac_kg',
        'date_mouvement', 'fournisseur', 'cout_total', 'motif', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementAliment::class,
            'quantite_kg' => 'decimal:2',
            'poids_sac_kg' => 'decimal:2',
            'cout_total' => 'decimal:2',
            'date_mouvement' => 'date',
        ];
    }

    public function aliment(): BelongsTo
    {
        return $this->belongsTo(Aliment::class, 'aliment_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}