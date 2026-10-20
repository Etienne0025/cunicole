<?php

namespace App\Models;

use App\Enums\StatutSaillie;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Saillie extends Model
{
    use HasFactory;

    protected $table = 'saillies';

    protected $fillable = [
        'femelle_id', 'male_id', 'date_saillie', 'statut',
        'date_palpation', 'boite_nids_le', 'utilisateur_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutSaillie::class,
            'date_saillie' => 'date',
            'date_palpation' => 'date',
            'boite_nids_le' => 'date',
        ];
    }

    public function femelle(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class, 'femelle_id');
    }

    public function male(): BelongsTo
    {
        return $this->belongsTo(Reproducteur::class, 'male_id');
    }

    public function portee(): HasOne
    {
        return $this->hasOne(Portee::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    /** Date prévue d'un jalon : palpation, boite_nids, mise_bas ou sevrage. */
    public function datePrevue(string $jalon): Carbon
    {
        return $this->date_saillie->copy()->addDays(config("cunicole.reproduction.jours_{$jalon}"));
    }

    public function joursDeRetard(string $jalon): int
    {
        return max(0, (int) $this->datePrevue($jalon)->diffInDays(today(), false));
    }
}