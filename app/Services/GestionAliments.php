<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TypeMouvementAliment;
use App\Enums\TypeTache;
use App\Models\Aliment;
use App\Models\ClotureJournee;
use App\Models\Distribution;
use App\Models\MouvementAliment;
use App\Models\Ration;
use App\Models\Tache;
use App\Models\Utilisateur;
use App\Notifications\AlerteStockAliment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class GestionAliments
{
    public function __construct(private EffectifsAlimentation $effectifs) {}

    public function recevoir(
        Aliment $aliment,
        int $sacs,
        float $poidsSac,
        CarbonInterface $date,
        ?string $fournisseur = null,
        ?float $cout = null,
        ?Utilisateur $par = null,
    ): MouvementAliment {
        return $this->appliquer($aliment, TypeMouvementAliment::Reception, round($sacs * $poidsSac, 2), $par, [
            'nombre_sacs' => $sacs,
            'poids_sac_kg' => $poidsSac,
            'date_mouvement' => $date,
            'fournisseur' => $fournisseur,
            'cout_total' => $cout,
        ]);
    }

    /** Correction d'inventaire : la variation peut être positive ou négative. */
    public function ajuster(Aliment $aliment, float $variation, string $motif, ?Utilisateur $par = null): MouvementAliment
    {
        $mouvement = $this->appliquer($aliment, TypeMouvementAliment::Ajustement, $variation, $par, [
            'date_mouvement' => today(),
            'motif' => $motif,
        ]);

        if ($variation < 0) {
            $this->notifierAlertes();
        }

        return $mouvement;
    }

    /** Enregistre la quantité réellement servie, décompte le stock et termine la tâche du jour. */
    public function distribuer(Ration $ration, float $quantiteServie, Utilisateur $par): Distribution
    {
        $jour = ($jour ?? today())->copy()->startOfDay();

        if (ClotureJournee::whereDate('date', $jour->toDateString())->exists()) {
            $this->refuser('action', "La journée est clôturée : l'alimentation ne peut plus être enregistrée.");
        }

        $distribution = DB::transaction(function () use ($ration, $quantiteServie, $par, $jour) {
            $ration = Ration::with('aliment')->lockForUpdate()->findOrFail($ration->id);

            if (! $ration->actif || ! $ration->aliment->actif) {
                $this->refuser('action', "Cette ration n'est plus active.");
            }

            $dejaFaite = Distribution::where('ration_id', $ration->id)
                ->whereDate('date_distribution', $jour->toDateString())
                ->exists();

            if ($dejaFaite) {
                $this->refuser('action', "Cette distribution est déjà enregistrée pour aujourd'hui.");
            }

            $nombre = $this->effectifs->pour($ration->categorie);

            if ($nombre === 0) {
                $this->refuser('action', "Aucun animal dans la catégorie « {$ration->categorie->libelle()} » aujourd'hui.");
            }

            $this->appliquer($ration->aliment, TypeMouvementAliment::Distribution, -$quantiteServie, $par, [
                'date_mouvement' => $jour,
                'motif' => "Distribution : {$ration->categorie->libelle()}",
            ]);

            return Distribution::create([
                'ration_id' => $ration->id,
                'aliment_id' => $ration->aliment_id,
                'categorie' => $ration->categorie,
                'date_distribution' => $jour,
                'nombre_animaux' => $nombre,
                'grammes_par_animal' => $ration->grammes_par_jour,
                'quantite_theorique_kg' => $ration->quantiteTheorique($nombre),
                'quantite_servie_kg' => round($quantiteServie, 2),
                'utilisateur_id' => $par->id,
            ]);
        });

        // L'événement « Distribution créée » a déjà terminé la tâche : on y ajoute l'auteur
        Tache::where('type', TypeTache::Distribution->value)
            ->where('source_type', $ration->getMorphClass())
            ->where('source_id', $ration->id)
            ->whereDate('date_prevue', $jour->toDateString())
            ->update(['faite_par_id' => $par->id]);

        $this->notifierAlertes();

        return $distribution;
    }

    /**
     * Situation de chaque aliment : consommation théorique par jour, autonomie en jours, alerte.
     *
     * @return Collection<int, array{aliment: Aliment, consommation: float, autonomie: ?float, bas: bool}>
     */
    public function etatStocks(): Collection
    {
        $effectifs = $this->effectifs->parCategorie();
        $seuil = (float) config('cunicole.alimentation.jours_autonomie_alerte', 3);
        $rations = Ration::where('actif', true)->get()->groupBy('aliment_id');

        return Aliment::orderByDesc('actif')->orderBy('nom')->get()->map(function (Aliment $aliment) use ($rations, $effectifs, $seuil) {
            $consommation = (float) ($rations->get($aliment->id) ?? collect())->sum(
                fn (Ration $r) => $r->grammes_par_jour * ($effectifs[$r->categorie->value] ?? 0) / 1000
            );

            $autonomie = $consommation > 0 ? (float) $aliment->stock_kg / $consommation : null;

            return [
                'aliment' => $aliment,
                'consommation' => round($consommation, 2),
                'autonomie' => $autonomie !== null ? round($autonomie, 1) : null,
                'bas' => $aliment->actif && $autonomie !== null && $autonomie < $seuil,
            ];
        });
    }

    /** Aliments actifs dont l'autonomie est inférieure au seuil. */
    public function alertes(): Collection
    {
        return $this->etatStocks()->filter(fn (array $etat) => $etat['bas'])->values();
    }

    /** Prévient les propriétaires, au plus une fois toutes les 24 heures par aliment. */
    public function notifierAlertes(): int
    {
        $envoyees = 0;
        $proprietaires = null;

        foreach ($this->alertes() as $alerte) {
            if (! Cache::add('alerte_stock_aliment_'.$alerte['aliment']->id, true, now()->addHours(24))) {
                continue;
            }

            $proprietaires ??= Utilisateur::where('role', Role::Proprietaire->value)->where('actif', true)->get();

            Notification::send($proprietaires, new AlerteStockAliment($alerte));
            $envoyees++;
        }

        return $envoyees;
    }

    private function appliquer(
        Aliment $aliment,
        TypeMouvementAliment $type,
        float $variation,
        ?Utilisateur $par,
        array $extra,
    ): MouvementAliment {
        return DB::transaction(function () use ($aliment, $type, $variation, $par, $extra) {
            $aliment = Aliment::lockForUpdate()->findOrFail($aliment->id);
            $stock = (float) $aliment->stock_kg;
            $nouveau = round($stock + $variation, 2);

            if ($nouveau < 0) {
                $disponible = number_format($stock, 2, ',', ' ');
                $besoin = number_format(abs($variation), 2, ',', ' ');

                $this->refuser('action', "Stock insuffisant de {$aliment->nom} : {$disponible} kg en stock pour {$besoin} kg demandés. Enregistrez d'abord la réception des sacs livrés, ou prévenez le propriétaire pour corriger l'inventaire.");
            }

            $aliment->update(['stock_kg' => $nouveau]);

            return MouvementAliment::create([
                'aliment_id' => $aliment->id,
                'type' => $type,
                'quantite_kg' => round($variation, 2),
                'utilisateur_id' => $par?->id,
                ...$extra,
            ]);
        });
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}