<?php

namespace App\Models;

use App\Enums\CibleVaccination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProtocoleVaccinal extends Model
{
    protected $table = 'protocoles_vaccinaux';

    protected $fillable = [
        'nom', 'produit_id', 'cible', 'age_premiere_dose_jours',
        'intervalle_rappel_jours', 'doses_par_animal', 'actif', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cible' => CibleVaccination::class,
            'age_premiere_dose_jours' => 'integer',
            'intervalle_rappel_jours' => 'integer',
            'doses_par_animal' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitVeterinaire::class, 'produit_id');
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class, 'protocole_id');
    }
}