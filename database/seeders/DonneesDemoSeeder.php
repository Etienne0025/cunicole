<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatutCage;
use App\Enums\TypeCage;
use App\Models\Batiment;
use App\Models\Cage;
use App\Models\Reproducteur;
use App\Models\Utilisateur;
use App\Services\PlacementCages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use App\Enums\StatutSaillie;
use App\Models\ObjectifNaissance;
use App\Models\Portee;
use App\Models\Saillie;
use App\Enums\StatutLot;
use App\Models\LotEngraissement;
use App\Enums\StatutTache;
use App\Enums\TypeTache;
use App\Models\ClotureJournee;
use App\Models\Tache;
use App\Services\GestionTaches;
use App\Enums\CauseMortalite;
use App\Enums\CibleVaccination;
use App\Enums\TypeProduit;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use App\Services\SoinsVeterinaires;
use App\Services\SuiviMortalite;
use App\Enums\CategorieAlimentation;
use App\Models\Aliment;
use App\Models\Distribution;
use App\Models\Ration;
use App\Services\EffectifsAlimentation;
use App\Services\GestionAliments;

use App\Enums\CategorieVente;
use App\Enums\ModePaiement;
use App\Enums\ModeTarif;
use App\Models\Charge;
use App\Models\Client;
use App\Models\ParametreVente;
use App\Models\Tarif;
use App\Models\Vente;
use App\Support\Monnaie;

class DonneesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $gestionnaire = Utilisateur::where('role', Role::Gestionnaire->value)->first();
        $placement = app(PlacementCages::class);

        $a = Batiment::create(['nom' => 'Bâtiment A']);
        $b = Batiment::create(['nom' => 'Bâtiment B']);

        $cagesMales = $this->cages($a, 'A', 1, 2, TypeCage::Male, 1);
        $cagesFemelles = $this->cages($a, 'A', 3, 8, TypeCage::Femelle, 1);
        $this->cages($b, 'B', 1, 10, TypeCage::Engraissement, 8);

        foreach ($cagesMales as $i => $cage) {
            $male = Reproducteur::factory()->male()->create(['matricule' => sprintf('M-%03d', $i + 1)]);
            $placement->placer($male, $cage, $gestionnaire, 'Mise en place initiale');
        }

        foreach ($cagesFemelles->take(5) as $i => $cage) {
            $femelle = Reproducteur::factory()->create(['matricule' => sprintf('F-%03d', $i + 1)]);
            $placement->placer($femelle, $cage, $gestionnaire, 'Mise en place initiale');
        }

        $this->cages($a, 'A', 9, 9, TypeCage::Femelle, 1);
        $this->cages($a, 'A', 10, 10, TypeCage::Male, 1);
        Cage::where('code', 'B-010')->update(['statut' => StatutCage::ANettoyer]);
        Cage::where('code', 'A-008')->update(['activee_le' => null]);
    
        $f = fn (string $matricule) => Reproducteur::where('matricule', $matricule)->firstOrFail();
$m1 = $f('M-001');
$m2 = $f('M-002');

// Palpation due (J+13)
Saillie::create(['femelle_id' => $f('F-001')->id, 'male_id' => $m1->id, 'date_saillie' => today()->subDays(13),
    'statut' => StatutSaillie::EnAttente, 'utilisateur_id' => $gestionnaire->id]);

// Gestante en cours (J+20)
Saillie::create(['femelle_id' => $f('F-002')->id, 'male_id' => $m2->id, 'date_saillie' => today()->subDays(20),
    'statut' => StatutSaillie::Gestante, 'date_palpation' => today()->subDays(8), 'utilisateur_id' => $gestionnaire->id]);

// Boîte à nids due et mise bas imminente (J+29)
Saillie::create(['femelle_id' => $f('F-003')->id, 'male_id' => $m1->id, 'date_saillie' => today()->subDays(29),
    'statut' => StatutSaillie::Gestante, 'date_palpation' => today()->subDays(17), 'utilisateur_id' => $gestionnaire->id]);

// Sevrage dû (J+62)
$saillie = Saillie::create(['femelle_id' => $f('F-004')->id, 'male_id' => $m2->id, 'date_saillie' => today()->subDays(62),
    'statut' => StatutSaillie::MiseBas, 'date_palpation' => today()->subDays(50),
    'boite_nids_le' => today()->subDays(34), 'utilisateur_id' => $gestionnaire->id]);
Portee::create(['saillie_id' => $saillie->id, 'date_mise_bas' => today()->subDays(31),
    'nes_vivants' => 8, 'morts_nes' => 1, 'utilisateur_id' => $gestionnaire->id]);

ObjectifNaissance::create(['annee' => today()->year, 'mois' => today()->month, 'objectif' => 40]);

   
$saillieLot = Saillie::create(['femelle_id' => $f('F-005')->id, 'male_id' => $m1->id,
    'date_saillie' => today()->subDays(140), 'statut' => StatutSaillie::Sevree,
    'date_palpation' => today()->subDays(128), 'utilisateur_id' => $gestionnaire->id]);

$porteeLot = Portee::create(['saillie_id' => $saillieLot->id, 'date_mise_bas' => today()->subDays(100),
    'nes_vivants' => 9, 'morts_nes' => 0, 'date_sevrage' => today()->subDays(70), 'sevres' => 9,
    'utilisateur_id' => $gestionnaire->id]);

$cageLot = Cage::where('code', 'B-001')->first();
LotEngraissement::create(['code' => 'LOT-DEMO-001', 'portee_id' => $porteeLot->id, 'cage_id' => $cageLot->id,
    'effectif_initial' => 9, 'effectif_actuel' => 9, 'date_debut' => today()->subDays(70),
    'statut' => StatutLot::EnCours]);
$placement->actualiser($cageLot);
// Journée d'hier, déjà clôturée, pour tester les rapports
$hier = today()->subDay();

Tache::create([
    'cle_unique' => 'demo:hier:1', 'type' => TypeTache::Quotidienne, 'date_prevue' => $hier,
    'titre' => "Contrôler l'eau et les abreuvoirs", 'statut' => StatutTache::Faite,
    'faite_le' => $hier->copy()->setTime(8, 15), 'faite_par_id' => $gestionnaire->id,
]);
Tache::create([
    'cle_unique' => 'demo:hier:2', 'type' => TypeTache::Quotidienne, 'date_prevue' => $hier,
    'titre' => 'Ronde de surveillance sanitaire', 'statut' => StatutTache::NonFaite,
    'justification' => 'Forte pluie en fin de journée, ronde reportée au lendemain matin.',
]);
ClotureJournee::create([
    'date' => $hier, 'utilisateur_id' => $gestionnaire->id,
    'cloturee_le' => $hier->copy()->setTime(18, 30),
    'total' => 2, 'faites' => 1, 'non_faites' => 1, 'commentaire' => 'Journée calme.',
]);

// Checklist d'aujourd'hui, générée à partir des données ci-dessus

// Santé : produits et calendrier FICTIFS, à remplacer par ceux prescrits par votre vétérinaire
$vaccin = ProduitVeterinaire::create([
    'nom' => 'Vaccin exemple (à remplacer)', 'type' => TypeProduit::Vaccin, 'unite' => 'dose',
    'stock_actuel' => 40, 'seuil_alerte' => 10, 'delai_attente_jours' => 0,
]);
$soin = ProduitVeterinaire::create([
    'nom' => 'Traitement exemple (à remplacer)', 'type' => TypeProduit::Traitement, 'unite' => 'ml',
    'stock_actuel' => 100, 'seuil_alerte' => 20, 'delai_attente_jours' => 7,
]);

ProtocoleVaccinal::create([
    'nom' => 'Rappel des reproducteurs (exemple)', 'produit_id' => $vaccin->id,
    'cible' => CibleVaccination::Reproducteur, 'age_premiere_dose_jours' => 0,
    'intervalle_rappel_jours' => 180, 'doses_par_animal' => 1,
]);
ProtocoleVaccinal::create([
    'nom' => 'Primo-vaccination des lots (exemple)', 'produit_id' => $vaccin->id,
    'cible' => CibleVaccination::Lot, 'age_premiere_dose_jours' => 30,
    'intervalle_rappel_jours' => null, 'doses_par_animal' => 1,
]);

// F-002 est en traitement : elle est bloquée pour la vente
app(SoinsVeterinaires::class)->traiter($f('F-002'), [
    'produit_id' => $soin->id, 'quantite' => 3, 'symptomes' => 'Éternuements (exemple)',
    'date_debut' => today()->toDateString(), 'duree_jours' => 3, 'delai_attente_jours' => 7,
], $gestionnaire);

// Un décès dans le lot de démonstration (le seuil de 5 % n'est pas encore atteint)
app(SuiviMortalite::class)->declarer(
    LotEngraissement::where('code', 'LOT-DEMO-001')->firstOrFail(),
    1, today()->subDays(2), CauseMortalite::Inconnue, null, $gestionnaire,
);


// Alimentation : aliments et quantités FICTIFS, à remplacer par ceux de votre ferme
$granules = Aliment::create(['nom' => 'Granulés exemple (à remplacer)', 'poids_sac_kg' => 25, 'notes' => 'Aliment fictif de démonstration']);
$complement = Aliment::create(['nom' => 'Complément exemple (à remplacer)', 'poids_sac_kg' => 5, 'notes' => 'Aliment fictif de démonstration']);

$rations = collect([
    [CategorieAlimentation::Male, $granules, 100],
    [CategorieAlimentation::FemelleVide, $granules, 100],
    [CategorieAlimentation::FemelleGestante, $granules, 120],
    [CategorieAlimentation::FemelleAllaitante, $granules, 200],
    [CategorieAlimentation::Engraissement, $granules, 80],
    [CategorieAlimentation::Engraissement, $complement, 50],
])->map(fn (array $ligne) => Ration::create([
    'categorie' => $ligne[0],
    'aliment_id' => $ligne[1]->id,
    'grammes_par_jour' => $ligne[2],
    'notes' => 'Valeur fictive de démonstration',
]));

$gestionAliments = app(GestionAliments::class);
$gestionAliments->recevoir($granules, 12, 25, today()->subDays(10), 'Fournisseur exemple', 90000, $gestionnaire);
// Stock volontairement bas pour montrer l'alerte (moins de 3 jours d'autonomie)
$gestionAliments->ajuster($complement, 0.8, 'Stock de démonstration', $gestionnaire);

// Historique des 7 derniers jours (le jour 4 est volontairement anormal : -35 %)
$effectifs = app(EffectifsAlimentation::class)->parCategorie();

foreach (range(7, 1) as $i) {
    foreach ($rations as $ration) {
        $nombre = $effectifs[$ration->categorie->value];

        if ($nombre === 0) {
            continue;
        }

        $theorique = $ration->quantiteTheorique($nombre);
        $facteur = $i === 4 ? 0.65 : 1 + (($i % 3) - 1) * 0.04;

        Distribution::create([
            'ration_id' => $ration->id,
            'aliment_id' => $ration->aliment_id,
            'categorie' => $ration->categorie,
            'date_distribution' => today()->subDays($i),
            'nombre_animaux' => $nombre,
            'grammes_par_animal' => $ration->grammes_par_jour,
            'quantite_theorique_kg' => $theorique,
            'quantite_servie_kg' => round($theorique * $facteur, 2),
            'utilisateur_id' => $gestionnaire->id,
        ]);
    }
}

$gestionAliments->notifierAlertes();

// Ventes : tarifs, conditions et historique FICTIFS, à remplacer par vos valeurs réelles
foreach ([
    [CategorieVente::Engraissement, ModeTarif::Kg, 2500],
    [CategorieVente::Reproducteur, ModeTarif::Unite, 15000],
    [CategorieVente::Reforme, ModeTarif::Kg, 2000],
] as [$categorieVente, $modeTarif, $prix]) {
    Tarif::create(['categorie' => $categorieVente, 'mode' => $modeTarif, 'prix' => $prix]);
}

ParametreVente::actuel()->update([
    'remise_max_pct' => 10, 'credit_autorise' => true,
    'objectif_hebdomadaire' => 60000, 'objectif_mensuel' => 250000,
]);

$clientA = Client::create(['nom' => 'Restaurant exemple (fictif)', 'telephone' => '00000000']);
$clientB = Client::create(['nom' => 'Client de démonstration (fictif)']);

$compteurs = [];

foreach ([83, 76, 69, 62, 55, 48, 41, 34, 27, 20, 13, 6, 2] as $rang => $joursPasses) {
    $date = today()->subDays($joursPasses);
    $poids = 8 + ($rang % 4) * 2.5;
    $montant = Monnaie::arrondir($poids * 2500);
    $client = $rang % 3 === 0 ? $clientA : ($rang % 3 === 1 ? $clientB : null);
    $aCredit = in_array($rang, [10, 12], true);                       // 2 ventes partiellement payées
    $paye = $aCredit ? Monnaie::arrondir($montant / 2) : $montant;
    $compteurs[$date->year] = ($compteurs[$date->year] ?? 0) + 1;

    $vente = Vente::create([
        'numero' => sprintf('V-%d-%04d', $date->year, $compteurs[$date->year]),
        'client_id' => $client?->id,
        'date_vente' => $date,
        'total_brut' => $montant, 'remise_pct' => 0, 'remise_montant' => 0, 'total_net' => $montant,
        'montant_paye' => $paye,
        'utilisateur_id' => $gestionnaire->id,
    ]);

    $vente->lignes()->create([
        'categorie' => CategorieVente::Engraissement,
        'description' => "Lapins d'engraissement (historique fictif)",
        'quantite' => 3 + $rang % 3, 'poids_kg' => $poids,
        'mode_prix' => ModeTarif::Kg, 'prix_applique' => 2500, 'montant' => $montant,
    ]);

    $vente->paiements()->create([
        'montant' => $paye,
        'mode' => $rang % 2 === 0 ? ModePaiement::Especes : ModePaiement::MobileMoney,
        'operateur' => $rang % 2 === 0 ? null : 'Mobile money exemple',
        'date_paiement' => $date,
        'utilisateur_id' => $gestionnaire->id,
    ]);
}

foreach ([0, 1, 2] as $decalage) {
    $mois = today()->startOfMonth()->subMonths($decalage);

    Charge::create(['categorie' => 'salaires', 'libelle' => 'Salaire (fictif)', 'montant' => 40000,
        'date_charge' => $mois, 'utilisateur_id' => null]);
    Charge::create(['categorie' => 'loyer', 'libelle' => 'Loyer du terrain (fictif)', 'montant' => 25000,
        'date_charge' => $mois, 'utilisateur_id' => null]);
    Charge::create(['categorie' => 'energie', 'libelle' => 'Électricité et eau (fictif)', 'montant' => 12000,
        'date_charge' => $mois, 'utilisateur_id' => null]);
}

foreach ([5, 12, 20, 33] as $joursPasses) {
    Charge::create(['categorie' => 'litiere', 'libelle' => 'Paille (fictif)', 'montant' => 4500,
        'date_charge' => today()->subDays($joursPasses), 'utilisateur_id' => $gestionnaire->id]);
}

app(GestionTaches::class)->synchroniser();
    
        }

    private function cages(Batiment $batiment, string $lettre, int $de, int $a, TypeCage $type, int $capacite): Collection
    {
        return collect(range($de, $a))->map(fn (int $n) => Cage::create([
            'batiment_id' => $batiment->id,
            'code' => sprintf('%s-%03d', $lettre, $n),
            'type' => $type,
            'capacite' => $capacite,
            'statut' => StatutCage::Vide,
            'activee_le' => now(),
        ]));
    }
}