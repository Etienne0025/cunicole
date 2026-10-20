<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementCage extends Model
{
    protected $table = 'mouvements_cages';

    protected $fillable = [
        'reproducteur_id', 'cage_origine_id', 'cage_destination_id',
        'code_origine', 'code_destination', 'utilisateur_id', 'motif', 'effectue_le',
    ];

    protected function casts(): array
    {
        return ['effectue_le' => 'datetime'];
    }

    public function reproducteur(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}