<?php

namespace App\Models;

use App\Enums\StatutOperationSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationSync extends Model
{
    use Prunable;

    protected $table = 'operations_sync';

    protected $fillable = ['cle', 'utilisateur_id', 'route', 'statut', 'message', 'effectue_le', 'traitee_le'];

    protected function casts(): array
    {
        return [
            'statut' => StatutOperationSync::class,
            'effectue_le' => 'datetime',
            'traitee_le' => 'datetime',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** Le journal est conservé 90 jours. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}