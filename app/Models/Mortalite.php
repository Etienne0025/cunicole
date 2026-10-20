<?php

namespace App\Models;

use App\Enums\CauseMortalite;
use App\Enums\CibleMortalite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mortalite extends Model
{
    protected $table = 'mortalites';

    protected $fillable = [
        'cible', 'reproducteur_id', 'lot_id', 'portee_id', 'code_cible',
        'nombre', 'date_deces', 'cause', 'precisions', 'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'cible' => CibleMortalite::class,
            'cause' => CauseMortalite::class,
            'date_deces' => 'date',
            'nombre' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}