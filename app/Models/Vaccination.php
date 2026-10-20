<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Vaccination extends Model
{
    protected $table = 'vaccinations';

    protected $fillable = [
        'protocole_id', 'produit_id', 'nom_vaccin', 'date_vaccination',
        'quantite_utilisee', 'anterieure', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_vaccination' => 'date',
            'quantite_utilisee' => 'decimal:2',
            'anterieure' => 'boolean',
        ];
    }

    public function vaccinable(): MorphTo
    {
        return $this->morphTo();
    }

    public function protocole(): BelongsTo
    {
        return $this->belongsTo(ProtocoleVaccinal::class, 'protocole_id');
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