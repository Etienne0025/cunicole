<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlocageConsanguinite extends Model
{
    protected $table = 'blocages_consanguinite';

    protected $fillable = ['femelle_id', 'male_id', 'lien', 'utilisateur_id'];

    public function femelle(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class, 'femelle_id');
    }

    public function male(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class, 'male_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}