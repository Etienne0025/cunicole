<?php

namespace App\Services;

use App\Enums\TypeMouvementAliment;
use App\Enums\TypeMouvementPharmacie;
use App\Models\Charge;
use App\Models\LigneVente;
use App\Models\MouvementAliment;
use App\Models\MouvementPharmacie;
use App\Models\Paiement;
use App\Models\ParametreVente;
use App\Models\Vente;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BilanFinancier
{
    /** @return array{debut: CarbonInterface, fin: CarbonInterface, libelle: string, cle: string} */
    public static function resoudrePeriode(?string $cle): array
    {
        $cle = in_array($cle, ['mois', 'mois_precedent', 'trimestre', 'annee'], true) ? $cle : 'mois';

        return match ($cle) {
            'mois_precedent' => [
                'debut' => today()->startOfMonth()->subMonth(),
                'fin' => today()->startOfMonth()->subMonth()->endOfMonth(),
                'libelle' => 'Mois précédent',
                'cle' => $cle,
            ],
            'trimestre' => [
                'debut' => today()->startOfMonth()->subMonths(2),
                'fin' => today()->endOfMonth(),
                'libelle' => '3 derniers mois',
                'cle' => $cle,
            ],
            'annee' => [
                'debut' => today()->startOfYear(),
                'fin' => today()->endOfYear(),
                'libelle' => 'Année en cours',
                'cle' => $cle,
            ],
            default => [
                'debut' => today()->startOfMonth(),
                'fin' => today()->endOfMonth(),
                'libelle' => 'Mois en cours',
                'cle' => $cle,
            ],
        };
    }

    public function chiffreAffaires(CarbonInterface $debut, CarbonInterface $fin): float
    {
        return (float) Vente::whereBetween('date_vente', $this->bornes($debut, $fin))->sum('total_net');
    }

    /**
     * Charges de la période : dépenses saisies + achats d'aliments + achats de la pharmacie.
     *
     * @return array{aliments: float, pharmacie: float, courantes: float, fixes: float, total: float, detail: Collection, sans_montant: int}
     */
    public function charges(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $bornes = $this->bornes($debut, $fin);

        $aliments = (float) MouvementAliment::where('type', TypeMouvementAliment::Reception->value)
            ->whereBetween('date_mouvement', $bornes)->sum('cout_total');

        $pharmacie = (float) MouvementPharmacie::where('type', TypeMouvementPharmacie::Achat->value)
            ->whereBetween('date_mouvement', $bornes)->sum('cout_total');

        $parCategorie = Charge::whereBetween('date_charge', $bornes)
            ->selectRaw('categorie, sum(montant) as total')
            ->groupBy('categorie')
            ->get();

        $courantes = (float) $parCategorie->filter(fn ($l) => ! $l->categorie->estFixe())->sum('total');
        $fixes = (float) $parCategorie->filter(fn ($l) => $l->categorie->estFixe())->sum('total');

        $detail = collect([
            ['libelle' => 'Aliments (réceptions de sacs)', 'montant' => $aliments],
            ['libelle' => 'Pharmacie (vaccins, traitements)', 'montant' => $pharmacie],
        ])->concat($parCategorie->map(fn ($l) => [
            'libelle' => $l->categorie->libelle(),
            'montant' => (float) $l->total,
        ]))->filter(fn (array $ligne) => $ligne['montant'] > 0)->values();

        $sansMontant = MouvementAliment::where('type', TypeMouvementAliment::Reception->value)
            ->whereBetween('date_mouvement', $bornes)->whereNull('cout_total')->count()
            + MouvementPharmacie::where('type', TypeMouvementPharmacie::Achat->value)
                ->whereBetween('date_mouvement', $bornes)->whereNull('cout_total')->count();

        return [
            'aliments' => $aliments,
            'pharmacie' => $pharmacie,
            'courantes' => $courantes,
            'fixes' => $fixes,
            'total' => $aliments + $pharmacie + $courantes + $fixes,
            'detail' => $detail,
            'sans_montant' => $sansMontant,
        ];
    }

    /** Bilan complet d'une période : Bénéfice net = Chiffre d'affaires − Charges. */
    public function periode(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $bornes = $this->bornes($debut, $fin);

        $ca = $this->chiffreAffaires($debut, $fin);
        $charges = $this->charges($debut, $fin);
        $benefice = $ca - $charges['total'];

        $parCategorie = LigneVente::query()
            ->join('ventes', 'ventes.id', '=', 'lignes_vente.vente_id')
            ->whereBetween('ventes.date_vente', $bornes)
            ->selectRaw('lignes_vente.categorie as categorie, sum(lignes_vente.quantite) as animaux, sum(coalesce(lignes_vente.poids_kg, 0)) as poids, sum(lignes_vente.montant) as montant')
            ->groupBy('lignes_vente.categorie')
            ->get()
            ->sortBy(fn ($ligne) => $ligne->categorie->ordre())
            ->values();

        return [
            'ca' => $ca,
            'remises' => (float) Vente::whereBetween('date_vente', $bornes)->sum('remise_montant'),
            'nb_ventes' => Vente::whereBetween('date_vente', $bornes)->count(),
            'nb_animaux' => (int) $parCategorie->sum('animaux'),
            'encaisse' => (float) Paiement::whereBetween('date_paiement', $bornes)->sum('montant'),
            'charges' => $charges,
            'benefice' => $benefice,
            'marge' => $ca > 0 ? round($benefice / $ca * 100, 1) : null,
            'ventes_par_categorie' => $parCategorie,
        ];
    }

    /** @return Collection<int, array{label: string, ca: float, charges: float, benefice: float}> */
    public function serieMensuelle(int $mois = 6): Collection
    {
        return collect(range($mois - 1, 0))->map(function (int $i) {
            $debut = today()->startOfMonth()->subMonths($i);
            $fin = $debut->copy()->endOfMonth();

            $ca = $this->chiffreAffaires($debut, $fin);
            $charges = $this->charges($debut, $fin)['total'];

            return [
                'label' => $debut->translatedFormat('M Y'),
                'ca' => $ca,
                'charges' => $charges,
                'benefice' => $ca - $charges,
            ];
        });
    }

    /** Somme restant à encaisser sur toutes les ventes. */
    public function creances(): float
    {
        return (float) Vente::whereColumn('montant_paye', '<', 'total_net')
            ->selectRaw('coalesce(sum(total_net - montant_paye), 0) as solde')
            ->value('solde');
    }

    /** @return array{semaine: array, mois: array} */
    public function progressionObjectifs(): array
    {
        $parametres = ParametreVente::actuel();

        $fabriquer = fn (float $realise, mixed $objectif) => [
            'realise' => $realise,
            'objectif' => $objectif !== null ? (float) $objectif : null,
            'pourcentage' => ($objectif !== null && (float) $objectif > 0)
                ? (int) round($realise / (float) $objectif * 100)
                : null,
        ];

        return [
            'semaine' => $fabriquer(
                $this->chiffreAffaires(today()->startOfWeek(), today()->endOfWeek()),
                $parametres->objectif_hebdomadaire,
            ),
            'mois' => $fabriquer(
                $this->chiffreAffaires(today()->startOfMonth(), today()->endOfMonth()),
                $parametres->objectif_mensuel,
            ),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function bornes(CarbonInterface $debut, CarbonInterface $fin): array
    {
        return [$debut->toDateString(), $fin->toDateString()];
    }
}