<?php

namespace App\Services;

use App\Enums\TypeMouvementPharmacie;
use App\Models\MouvementPharmacie;
use App\Models\ProduitVeterinaire;
use App\Models\Utilisateur;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Pharmacie
{
    public function entrer(
        ProduitVeterinaire $produit,
        float $quantite,
        CarbonInterface $date,
        ?string $fournisseur = null,
        ?float $cout = null,
        ?Utilisateur $par = null,
    ): MouvementPharmacie {
        return $this->appliquer($produit, TypeMouvementPharmacie::Achat, abs($quantite), $par, [
            'date_mouvement' => $date,
            'fournisseur' => $fournisseur,
            'cout_total' => $cout,
        ]);
    }

  public function consommer(ProduitVeterinaire $produit, float $quantite, ?Utilisateur $par, string $motif, ?CarbonInterface $date = null): MouvementPharmacie
{
    return $this->appliquer($produit, TypeMouvementPharmacie::Utilisation, -abs($quantite), $par, [
        'date_mouvement' => $date?->copy()->startOfDay() ?? today(),
        'motif' => $motif,
    ]);
}

    /** Correction d'inventaire : la variation peut être positive ou négative. */
    public function ajuster(ProduitVeterinaire $produit, float $variation, string $motif, ?Utilisateur $par = null): MouvementPharmacie
    {
        return $this->appliquer($produit, TypeMouvementPharmacie::Ajustement, $variation, $par, [
            'date_mouvement' => today(),
            'motif' => $motif,
        ]);
    }

    private function appliquer(
        ProduitVeterinaire $produit,
        TypeMouvementPharmacie $type,
        float $variation,
        ?Utilisateur $par,
        array $extra,
    ): MouvementPharmacie {
        return DB::transaction(function () use ($produit, $type, $variation, $par, $extra) {
            $produit = ProduitVeterinaire::lockForUpdate()->findOrFail($produit->id);
            $stock = (float) $produit->stock_actuel;
            $nouveau = round($stock + $variation, 2);

            if ($nouveau < 0) {
                $disponible = number_format($stock, 2, ',', ' ');
                $besoin = number_format(abs($variation), 2, ',', ' ');

                throw ValidationException::withMessages([
                    'action' => "Stock insuffisant de {$produit->nom} : {$disponible} {$produit->unite} disponible(s) pour {$besoin} nécessaire(s). Demandez au propriétaire de réapprovisionner la pharmacie.",
                ]);
            }

            $produit->update(['stock_actuel' => $nouveau]);

            return MouvementPharmacie::create([
                'produit_id' => $produit->id,
                'type' => $type,
                'quantite' => round($variation, 2),
                'utilisateur_id' => $par?->id,
                ...$extra,
            ]);
        });
    }
}