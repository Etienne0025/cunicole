<?php

namespace App\Models;

use App\Enums\CategorieVente;
use App\Enums\ModeTarif;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LigneVente extends Model
{
    protected $table = 'lignes_vente';

    protected $fillable = [
        'vente_id', 'vendable_type', 'vendable_id', 'categorie', 'description',
        'quantite', 'poids_kg', 'mode_prix', 'prix_applique', 'montant',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieVente::class,
            'mode_prix' => ModeTarif::class,
            'poids_kg' => 'decimal:2',
            'prix_applique' => 'decimal:2',
            'montant' => 'decimal:2',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function vendable(): MorphTo
    {
        return $this->morphTo();
    }
}