<?php

namespace App\Models;

use App\Enums\TypeMouvementPharmacie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementPharmacie extends Model
{
    protected $table = 'mouvements_pharmacie';

    protected $fillable = [
        'produit_id', 'type', 'quantite', 'date_mouvement',
        'fournisseur', 'cout_total', 'motif', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeMouvementPharmacie::class,
            'quantite' => 'decimal:2',
            'cout_total' => 'decimal:2',
            'date_mouvement' => 'date',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitVeterinaire::class, 'produit_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}