<?php

namespace App\Models;

use App\Models\Concerns\ASuiviSanitaire;
use App\Enums\OrigineReproducteur;
use App\Enums\SexeAnimal;
use App\Enums\StatutReproducteur;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reproducteur extends Model
{
    use HasFactory;

    protected $table = 'reproducteurs';

    protected $fillable = [
        'matricule', 'nom', 'sexe', 'race', 'date_naissance', 'origine',
        'mere_id', 'pere_id', 'cage_id', 'statut',
        'date_entree', 'date_sortie', 'motif_sortie', 'notes','valide_le', 'valide_par_id', 'lot_origine_id',
    ];

    protected function casts(): array
    {
        return [
            'sexe' => SexeAnimal::class,
            'statut' => StatutReproducteur::class,
            'origine' => OrigineReproducteur::class,
            'date_naissance' => 'date',
            'date_entree' => 'date',
            'date_sortie' => 'date',
            'valide_le' => 'datetime',
        ];
    }

    public function cage(): BelongsTo
    {
        return $this->belongsTo(Cage::class);
    }

    public function mere(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mere_id');
    }

    public function pere(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pere_id');
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementCage::class);
    }

    public function pesees(): HasMany
    {
        return $this->hasMany(Pesee::class);
    }

    public function estActif(): bool
    {
        return $this->statut === StatutReproducteur::Actif;
    }

    public function ageEnMois(): int
    {
        return (int) $this->date_naissance->diffInMonths(now());
    }

    public function lotOrigine(): BelongsTo
{
    return $this->belongsTo(LotEngraissement::class, 'lot_origine_id');
}

public function estValide(): bool
{
    return $this->valide_le !== null;
}
}