<?php

namespace App\Services;

use App\Enums\StatutCage;
use App\Enums\TypeCage;
use App\Models\Cage;
use App\Models\MouvementCage;
use App\Models\Reproducteur;
use App\Models\Utilisateur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Enums\StatutLot;

class PlacementCages
{
    public function placer(Reproducteur $reproducteur, Cage $cage, ?Utilisateur $par = null, ?string $motif = null): void
    {
        DB::transaction(function () use ($reproducteur, $cage, $par, $motif) {
            $cage = Cage::lockForUpdate()->findOrFail($cage->id);
            $reproducteur->refresh();

            $this->verifier($reproducteur, $cage);

            $origine = $reproducteur->cage_id ? Cage::find($reproducteur->cage_id) : null;

            $reproducteur->update(['cage_id' => $cage->id]);
            $this->enregistrer($reproducteur, $origine, $cage, $par, $motif);

            $this->actualiser($cage);
            if ($origine) {
                $this->actualiser($origine);
            }
        });
    }

    public function retirer(Reproducteur $reproducteur, ?Utilisateur $par = null, ?string $motif = null): void
    {
        DB::transaction(function () use ($reproducteur, $par, $motif) {
            $reproducteur->refresh();

            if (! $reproducteur->cage_id) {
                return;
            }

            $origine = Cage::lockForUpdate()->find($reproducteur->cage_id);

            $reproducteur->update(['cage_id' => null]);
            $this->enregistrer($reproducteur, $origine, null, $par, $motif);

            if ($origine) {
                $this->actualiser($origine);
            }
        });
    }

    /** Recalcule le statut d'une cage selon son contenu réel. */
    public function actualiser(Cage $cage): void
    {
        $cage->refresh();
        $nombre = $cage->reproducteurs()->count()
    + $cage->lots()->where('statut', StatutLot::EnCours->value)->count();
        if ($nombre > 0 && $cage->statut !== StatutCage::Occupee) {
            $cage->update(['statut' => StatutCage::Occupee]);
        } elseif ($nombre === 0 && $cage->statut === StatutCage::Occupee) {
            $cage->update(['statut' => StatutCage::ANettoyer]);
        }
    }

    /** Cages où l'animal peut être placé maintenant. */
    public function cagesDisponibles(Reproducteur $reproducteur): Collection
    {
        return Cage::with('batiment')
            ->whereNotNull('activee_le')
            ->where('statut', '!=', StatutCage::ANettoyer->value)
            ->whereIn('type', array_map(fn (TypeCage $t) => $t->value, TypeCage::accepteursDe($reproducteur->sexe)))
            ->withCount('reproducteurs')
            ->orderBy('code')
            ->get()
            ->filter(fn (Cage $c) => $c->reproducteurs_count < $c->capacite && $c->id !== $reproducteur->cage_id)
            ->values();
    }

    private function verifier(Reproducteur $reproducteur, Cage $cage): void
    {
        if (! $reproducteur->estActif()) {
            $this->refuser("Cet animal n'est plus dans le cheptel actif.");
        }
        if ($reproducteur->cage_id === $cage->id) {
            $this->refuser("L'animal est déjà dans cette cage.");
        }
        if (! $cage->estActivee()) {
            $this->refuser("La cage {$cage->code} n'est pas encore activée.");
        }
        if ($cage->statut === StatutCage::ANettoyer) {
            $this->refuser("La cage {$cage->code} doit d'abord être nettoyée.");
        }
        if (! in_array($cage->type, TypeCage::accepteursDe($reproducteur->sexe), true)) {
            $this->refuser("La cage {$cage->code} ({$cage->type->libelle()}) ne convient pas à ce reproducteur.");
        }
        if ($cage->reproducteurs()->count() >= $cage->capacite) {
            $this->refuser("La cage {$cage->code} est pleine.");
        }
    }

    private function refuser(string $message): never
    {
        throw ValidationException::withMessages(['cage_id' => $message]);
    }

    private function enregistrer(Reproducteur $reproducteur, ?Cage $origine, ?Cage $destination, ?Utilisateur $par, ?string $motif): void
    {
        MouvementCage::create([
            'reproducteur_id' => $reproducteur->id,
            'cage_origine_id' => $origine?->id,
            'cage_destination_id' => $destination?->id,
            'code_origine' => $origine?->code,
            'code_destination' => $destination?->code,
            'utilisateur_id' => $par?->id,
            'motif' => $motif,
            'effectue_le' => now(),
        ]);
    }
}