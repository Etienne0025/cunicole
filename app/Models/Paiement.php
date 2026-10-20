<?php

namespace App\Models;

use App\Enums\ModePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = ['vente_id', 'montant', 'mode', 'operateur', 'reference', 'date_paiement', 'utilisateur_id'];

    protected function casts(): array
    {
        return [
            'mode' => ModePaiement::class,
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}