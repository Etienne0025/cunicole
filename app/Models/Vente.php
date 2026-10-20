<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $table = 'ventes';

    protected $fillable = [
        'numero', 'jeton', 'client_id', 'date_vente', 'total_brut', 'remise_pct',
        'remise_montant', 'total_net', 'montant_paye', 'utilisateur_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_vente' => 'date',
            'total_brut' => 'decimal:2',
            'remise_pct' => 'decimal:2',
            'remise_montant' => 'decimal:2',
            'total_net' => 'decimal:2',
            'montant_paye' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneVente::class, 'vente_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'vente_id');
    }

    public function solde(): float
    {
        return max(0.0, round((float) $this->total_net - (float) $this->montant_paye, 2));
    }

    public function statutPaiement(): StatutPaiement
    {
        if ($this->solde() <= 0) {
            return StatutPaiement::Payee;
        }

        return (float) $this->montant_paye > 0 ? StatutPaiement::Partielle : StatutPaiement::Impayee;
    }
}