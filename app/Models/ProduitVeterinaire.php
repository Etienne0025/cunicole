<?php

namespace App\Models;

use App\Enums\TypeProduit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduitVeterinaire extends Model
{
    protected $table = 'produits_veterinaires';

    protected $fillable = [
        'nom', 'type', 'unite', 'stock_actuel', 'seuil_alerte',
        'delai_attente_jours', 'actif', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeProduit::class,
            'stock_actuel' => 'decimal:2',
            'seuil_alerte' => 'decimal:2',
            'delai_attente_jours' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementPharmacie::class, 'produit_id');
    }

    public function stockBas(): bool
    {
        return (float) $this->seuil_alerte > 0 && (float) $this->stock_actuel <= (float) $this->seuil_alerte;
    }
}