<?php

namespace App\Models;

use App\Enums\CategorieVente;
use App\Enums\ModeTarif;
use App\Support\Monnaie;
use Illuminate\Database\Eloquent\Model;

class Tarif extends Model
{
    protected $table = 'tarifs';

    protected $fillable = ['categorie', 'mode', 'prix', 'actif'];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieVente::class,
            'mode' => ModeTarif::class,
            'prix' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    /** Montant d'une ligne : poids × prix au kg, ou nombre de pièces × prix à la pièce. */
    public function montantPour(int $quantite, ?float $poidsKg): float
    {
        $brut = $this->mode === ModeTarif::Kg
            ? (float) $poidsKg * (float) $this->prix
            : $quantite * (float) $this->prix;

        return Monnaie::arrondir($brut);
    }
}