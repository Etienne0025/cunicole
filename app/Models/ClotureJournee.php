<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClotureJournee extends Model
{
    protected $table = 'clotures_journees';

    protected $fillable = [
        'date', 'utilisateur_id', 'cloturee_le', 'total', 'faites', 'non_faites', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'cloturee_le' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public function tauxReussite(): ?int
    {
        return $this->total > 0 ? (int) round($this->faites / $this->total * 100) : null;
    }
}