<?php

namespace App\Services;

use App\Enums\CategorieVente;
use App\Enums\ModePaiement;
use App\Enums\ModeTarif;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Models\Client;
use App\Models\LotEngraissement;
use App\Models\Paiement;
use App\Models\ParametreVente;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Tarif;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Support\Monnaie;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GestionVentes
{
    public function __construct(
        private PlacementCages $placement,
        private CycleReproduction $cycle,
    ) {}

    /**
     * @param  array{
     *     lignes: array<int, array{type: string, id: int|string, quantite?: int|string|null, poids_kg?: float|string|null}>,
     *     client_id?: int|string|null, nouveau_client_nom?: ?string, nouveau_client_telephone?: ?string,
     *     remise_pct?: float|string|null, montant_paye?: float|string|null, mode_paiement?: ?string,
     *     operateur?: ?string, reference?: ?string, notes?: ?string, jeton?: ?string
     * }  $donnees
     */
    public function enregistrer(array $donnees, Utilisateur $par, ?CarbonInterface $date = null): Vente
    {
        $date = ($date ?? today())->copy()->startOfDay();

        if ($date->isFuture()) {
            $this->refuser('date', 'La date de la vente ne peut pas être dans le futur.');
        }

        return DB::transaction(function () use ($donnees, $par, $date) {
            $parametres = ParametreVente::actuel();
            $tarifs = Tarif::where('actif', true)->get()->keyBy(fn (Tarif $t) => $t->categorie->value);

            // --- Remise ---
            $remisePct = round((float) ($donnees['remise_pct'] ?? 0), 2);
            $remiseMax = (float) $parametres->remise_max_pct;

            if ($remisePct > $remiseMax) {
                $this->refuser('remise_pct', $remiseMax <= 0
                    ? "Aucune remise n'est autorisée. Demandez au propriétaire de l'autoriser."
                    : 'La remise est limitée à '.rtrim(rtrim(number_format($remiseMax, 2, ',', ''), '0'), ',').' %. Pour une remise plus importante, demandez l\'autorisation du propriétaire.');
            }

            // --- Lignes (les lots et animaux sont verrouillés pour éviter toute double vente) ---
            $lignes = [];
            $vus = [];

            foreach (array_values($donnees['lignes'] ?? []) as $i => $ligne) {
                $lignes[] = $this->preparerLigne($i, $ligne, $tarifs, $vus);
            }

            if ($lignes === []) {
                $this->refuser('lignes', 'Ajoutez au moins un article à la vente.');
            }

            // --- Totaux ---
            $brut = Monnaie::arrondir(collect($lignes)->sum('montant'));
            $remiseMontant = Monnaie::arrondir($brut * $remisePct / 100);
            $net = Monnaie::arrondir($brut - $remiseMontant);

            if ($net <= 0) {
                $this->refuser('lignes', 'Le montant de la vente est nul.');
            }

            // --- Paiement et crédit ---
            $paye = Monnaie::arrondir($donnees['montant_paye'] ?? 0);

            if ($paye < 0) {
                $this->refuser('montant_paye', 'Le montant payé ne peut pas être négatif.');
            }
            if ($paye > $net) {
                $this->refuser('montant_paye', 'Le montant payé ('.Monnaie::format($paye).') dépasse le total à payer ('.Monnaie::format($net).').');
            }

            $mode = ModePaiement::tryFrom((string) ($donnees['mode_paiement'] ?? ''));

            if ($paye > 0 && $mode === null) {
                $this->refuser('mode_paiement', 'Choisissez le mode de paiement.');
            }

            $nouveauNom = trim((string) ($donnees['nouveau_client_nom'] ?? ''));
            $aUnClient = ! empty($donnees['client_id']) || $nouveauNom !== '';

            if ($paye < $net) {
                if (! $parametres->credit_autorise) {
                    $this->refuser('montant_paye', "Les ventes à crédit ne sont pas autorisées : le paiement doit couvrir tout le montant ({$this->montant($net)}). Demandez au propriétaire d'autoriser le crédit.");
                }
                if (! $aUnClient) {
                    $this->refuser('client_id', 'Un client enregistré est obligatoire pour une vente à crédit.');
                }
            }

            // --- Client ---
            $client = null;

            if ($nouveauNom !== '') {
                $client = Client::create([
                    'nom' => $nouveauNom,
                    'telephone' => $donnees['nouveau_client_telephone'] ?? null,
                ]);
            } elseif (! empty($donnees['client_id'])) {
                $client = Client::findOrFail($donnees['client_id']);
            }

            // --- Enregistrement ---
            $vente = Vente::create([
                'numero' => $this->prochainNumero($date),
                'jeton' => $donnees['jeton'] ?? null,
                'client_id' => $client?->id,
                'date_vente' => $date,
                'total_brut' => $brut,
                'remise_pct' => $remisePct,
                'remise_montant' => $remiseMontant,
                'total_net' => $net,
                'montant_paye' => $paye,
                'utilisateur_id' => $par->id,
                'notes' => $donnees['notes'] ?? null,
            ]);

            foreach ($lignes as $ligne) {
                $sujet = $ligne['sujet'];

                $vente->lignes()->create([
                    'vendable_type' => $sujet->getMorphClass(),
                    'vendable_id' => $sujet->getKey(),
                    'categorie' => $ligne['categorie'],
                    'description' => $ligne['description'],
                    'quantite' => $ligne['quantite'],
                    'poids_kg' => $ligne['poids'],
                    'mode_prix' => $ligne['tarif']->mode,
                    'prix_applique' => $ligne['tarif']->prix,
                    'montant' => $ligne['montant'],
                ]);

                $this->sortirDuCheptel($sujet, $ligne['quantite'], $vente, $date, $par);
            }

            if ($paye > 0) {
                $vente->paiements()->create([
                    'montant' => $paye,
                    'mode' => $mode,
                    'operateur' => $donnees['operateur'] ?? null,
                    'reference' => $donnees['reference'] ?? null,
                    'date_paiement' => $date,
                    'utilisateur_id' => $par->id,
                ]);
            }

            return $vente->load(['client', 'lignes', 'paiements']);
        });
    }

    /** Encaisse un paiement (total ou partiel) sur une vente à crédit. */
    public function encaisser(Vente $vente, array $donnees, Utilisateur $par, ?CarbonInterface $date = null): Paiement
    {
        $mode = ModePaiement::tryFrom((string) ($donnees['mode_paiement'] ?? ''));

        if ($mode === null) {
            $this->refuser('mode_paiement', 'Choisissez le mode de paiement.');
        }

        $montant = Monnaie::arrondir($donnees['montant'] ?? 0);

        if ($montant <= 0) {
            $this->refuser('montant', 'Saisissez un montant supérieur à zéro.');
        }

        return DB::transaction(function () use ($vente, $donnees, $par, $date, $mode, $montant) {
            $vente = Vente::lockForUpdate()->findOrFail($vente->id);
            $solde = $vente->solde();

            if ($solde <= 0) {
                $this->refuser('montant', 'Cette vente est déjà entièrement payée.');
            }
            if ($montant > $solde) {
                $this->refuser('montant', 'Le montant dépasse le solde restant ('.Monnaie::format($solde).').');
            }

            $paiement = $vente->paiements()->create([
                'montant' => $montant,
                'mode' => $mode,
                'operateur' => $donnees['operateur'] ?? null,
                'reference' => $donnees['reference'] ?? null,
                'date_paiement' => ($date ?? today())->copy()->startOfDay(),
                'utilisateur_id' => $par->id,
            ]);

            $vente->update(['montant_paye' => round((float) $vente->montant_paye + $montant, 2)]);

            return $paiement;
        });
    }

    // ------------------------------------------------------------------

    /**
     * @param  array<string, bool>  $vus
     * @return array{sujet: Model, categorie: CategorieVente, tarif: Tarif, quantite: int, poids: ?float, montant: float, description: string}
     */
    private function preparerLigne(int $i, array $ligne, Collection $tarifs, array &$vus): array
    {
        $champ = "lignes.{$i}.id";
        $type = $ligne['type'] ?? null;
        $id = (int) ($ligne['id'] ?? 0);

        if (! in_array($type, ['lot', 'reproducteur'], true) || $id <= 0) {
            $this->refuser($champ, 'Choisissez un lot ou un animal pour chaque article.');
        }

        $cle = "{$type}-{$id}";

        if (isset($vus[$cle])) {
            $this->refuser($champ, 'Cet article figure déjà dans la vente : regroupez-le sur une seule ligne.');
        }
        $vus[$cle] = true;

        [$sujet, $categorie, $quantite, $description] = $type === 'lot'
            ? $this->verifierLot($i, $id, (int) ($ligne['quantite'] ?? 0))
            : $this->verifierAnimal($i, $id);

        $tarif = $tarifs->get($categorie->value);

        if ($tarif === null) {
            $this->refuser($champ, "Aucun tarif n'est fixé pour « {$categorie->libelle()} ». Demandez au propriétaire de le définir.");
        }

        $poids = isset($ligne['poids_kg']) && $ligne['poids_kg'] !== '' ? round((float) $ligne['poids_kg'], 2) : null;

        if ($tarif->mode === ModeTarif::Kg && ($poids === null || $poids <= 0)) {
            $this->refuser("lignes.{$i}.poids_kg", "Indiquez le poids pesé pour {$description} (prix au kg).");
        }
        if ($poids !== null && $poids > 15 * $quantite) {
            $this->refuser("lignes.{$i}.poids_kg", "Le poids saisi pour {$description} semble trop élevé : vérifiez la pesée.");
        }

        $montant = $tarif->montantPour($quantite, $poids);

        if ($montant <= 0) {
            $this->refuser($champ, "Le montant de l'article {$description} est nul.");
        }

        return [
            'sujet' => $sujet,
            'categorie' => $categorie,
            'tarif' => $tarif,
            'quantite' => $quantite,
            'poids' => $poids,
            'montant' => $montant,
            'description' => $description,
        ];
    }

    /** @return array{0: LotEngraissement, 1: CategorieVente, 2: int, 3: string} */
    private function verifierLot(int $i, int $id, int $quantite): array
    {
        $champ = "lignes.{$i}.id";
        $lot = LotEngraissement::lockForUpdate()->find($id);

        if ($lot === null || $lot->statut !== StatutLot::EnCours) {
            $this->refuser($champ, "Ce lot n'est plus disponible à la vente.");
        }
        if ($quantite < 1) {
            $this->refuser("lignes.{$i}.quantite", "Indiquez le nombre de lapins vendus du lot {$lot->code}.");
        }
        if ($quantite > $lot->effectif_actuel) {
            $this->refuser("lignes.{$i}.quantite", "Le lot {$lot->code} ne compte que {$lot->effectif_actuel} lapin(s).");
        }

        $this->verifierSanitaire($lot, $champ, "Le lot {$lot->code}");

        return [$lot, CategorieVente::Engraissement, $quantite, "{$lot->code} · {$quantite} lapin(s)"];
    }

    /** @return array{0: Reproducteur, 1: CategorieVente, 2: int, 3: string} */
    private function verifierAnimal(int $i, int $id): array
    {
        $champ = "lignes.{$i}.id";
        $animal = Reproducteur::lockForUpdate()->find($id);

        if ($animal === null) {
            $this->refuser($champ, 'Animal introuvable.');
        }

        $categorie = match ($animal->statut) {
            StatutReproducteur::Actif => CategorieVente::Reproducteur,
            StatutReproducteur::Reforme => CategorieVente::Reforme,
            default => null,
        };

        if ($categorie === null) {
            $this->refuser($champ, "{$animal->matricule} n'est plus disponible à la vente (statut : {$animal->statut->libelle()}).");
        }
        if ($animal->statut === StatutReproducteur::Actif && ! $animal->estValide()) {
            $this->refuser($champ, "{$animal->matricule} n'a pas encore été validé par le propriétaire.");
        }
        if (Saillie::where('femelle_id', $animal->id)->where('statut', StatutSaillie::MiseBas->value)->exists()) {
            $this->refuser($champ, "{$animal->matricule} allaite une portée : enregistrez d'abord le sevrage.");
        }

        $this->verifierSanitaire($animal, $champ, $animal->matricule);

        $libelle = ($categorie === CategorieVente::Reforme ? 'Animal réformé ' : 'Reproducteur ').$animal->matricule.' ('.$animal->sexe->libelle().')';

        return [$animal, $categorie, 1, $libelle];
    }

    private function verifierSanitaire(Model $sujet, string $champ, string $nom): void
    {
        $blocage = $sujet->traitementBloquant();

        if ($blocage !== null) {
            $this->refuser($champ, "{$nom} est bloqué pour la vente jusqu'au {$blocage->date_fin_attente->format('d/m/Y')} (délai d'attente sanitaire : {$blocage->nom_traitement}).");
        }
    }

    private function sortirDuCheptel(Model $sujet, int $quantite, Vente $vente, CarbonInterface $date, Utilisateur $par): void
    {
        if ($sujet instanceof LotEngraissement) {
            $reste = $sujet->effectif_actuel - $quantite;

            $sujet->update([
                'effectif_actuel' => $reste,
                'statut' => $reste === 0 ? StatutLot::Termine : StatutLot::EnCours,
            ]);

            // Lot vide : sa cage passe « à nettoyer »
            if ($reste === 0 && $sujet->cage) {
                $this->placement->actualiser($sujet->cage);
            }

            return;
        }

        if ($sujet->statut === StatutReproducteur::Actif) {
            $this->placement->retirer($sujet, $par, "Vente {$vente->numero}");
            $this->cycle->interrompreSaillies($sujet);

            $sujet->update([
                'statut' => StatutReproducteur::Vendu,
                'date_sortie' => $date,
                'motif_sortie' => "Vendu (vente {$vente->numero})",
            ]);

            return;
        }

        // Animal déjà réformé : on garde sa date et son motif de réforme
        $motif = trim(($sujet->motif_sortie ? $sujet->motif_sortie.' · ' : '')."Vendu (vente {$vente->numero})");

        $sujet->update([
            'statut' => StatutReproducteur::Vendu,
            'motif_sortie' => Str::limit($motif, 250, ''),
        ]);
    }

    private function prochainNumero(CarbonInterface $date): string
    {
        $prefixe = 'V-'.$date->year.'-';

        $dernier = Vente::where('numero', 'like', $prefixe.'%')
            ->pluck('numero')
            ->map(fn (string $numero) => (int) Str::afterLast($numero, '-'))
            ->max() ?? 0;

        return sprintf('%s%04d', $prefixe, $dernier + 1);
    }

    private function montant(float $montant): string
    {
        return Monnaie::format($montant);
    }

    private function refuser(string $champ, string $message): never
    {
        throw ValidationException::withMessages([$champ => $message]);
    }
}