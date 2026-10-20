<?php

namespace App\Models;

use App\Enums\StatutTache;
use App\Enums\TypeTache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Tache extends Model
{
    use HasFactory;

    protected $table = 'taches';

    protected $fillable = [
        'cle_unique', 'type', 'date_prevue', 'titre', 'description',
        'source_type', 'source_id', 'statut', 'faite_le', 'faite_par_id',
        'automatique', 'justification','protocole_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeTache::class,
            'statut' => StatutTache::class,
            'date_prevue' => 'date',
            'faite_le' => 'datetime',
            'automatique' => 'boolean',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function faitePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'faite_par_id');
    }

    /** Adresse de la page où réaliser la tâche (espace gestionnaire). */
 public function lien(): ?string
{
    $source = $this->source;

    if ($source instanceof Traitement) {
        $source = $source->traitable;
    }

    return match (true) {
        $source instanceof Saillie => route('gestionnaire.saillies.show', $source),
        $source instanceof Cage => route('gestionnaire.cages.show', $source),
        $source instanceof LotEngraissement => route('gestionnaire.lots.show', $source),
        $source instanceof Reproducteur => route('gestionnaire.reproducteurs.show', $source),
        $source instanceof Ration => route('gestionnaire.alimentation.index'),
        default => null,
    };
}


    public function protocole(): BelongsTo
{
    return $this->belongsTo(ProtocoleVaccinal::class, 'protocole_id');
}
}