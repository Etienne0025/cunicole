<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Traitement extends Model
{
    protected $table = 'traitements';

    protected $fillable = [
        'produit_id', 'nom_traitement', 'symptomes', 'date_debut', 'duree_jours',
        'delai_attente_jours', 'date_fin_traitement', 'date_fin_attente',
        'quantite_utilisee', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin_traitement' => 'date',
            'date_fin_attente' => 'date',
            'duree_jours' => 'integer',
            'delai_attente_jours' => 'integer',
            'quantite_utilisee' => 'decimal:2',
        ];
    }

    public function traitable(): MorphTo
    {
        return $this->morphTo();
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(ProduitVeterinaire::class, 'produit_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public function libelleCible(): string
    {
        $cible = $this->traitable;

        return $cible instanceof Reproducteur ? $cible->matricule : ($cible?->code ?? '-');
    }

    /** Page du gestionnaire où se trouve l'animal ou le lot traité. */
    public function lien(): ?string
    {
        $cible = $this->traitable;

        return match (true) {
            $cible instanceof Reproducteur => route('gestionnaire.reproducteurs.show', $cible),
            $cible instanceof LotEngraissement => route('gestionnaire.lots.show', $cible),
            default => null,
        };
    }
}