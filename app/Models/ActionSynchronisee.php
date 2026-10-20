<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionSynchronisee extends Model
{
    use MassPrunable;

    protected $table = 'actions_synchronisees';

    protected $fillable = [
        'uuid', 'utilisateur_id', 'type', 'libelle', 'statut', 'message',
        'redirection', 'payload', 'effectuee_le', 'traitee_le',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'effectuee_le' => 'datetime',
            'traitee_le' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** Le journal est purgé au bout de 90 jours. */
    public function prunable(): Builder
    {
        return static::where('traitee_le', '<', now()->subDays(90));
    }
}