<?php

namespace App\Models;

use App\Enums\CategorieCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    protected $table = 'charges';

    protected $fillable = ['categorie', 'libelle', 'montant', 'date_charge', 'notes', 'utilisateur_id'];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieCharge::class,
            'montant' => 'decimal:2',
            'date_charge' => 'date',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}