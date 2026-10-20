<?php

use App\Enums\CibleVaccination;
use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\StatutTache;
use App\Enums\TypeCage;
use App\Enums\TypeProduit;
use App\Models\Cage;
use App\Models\LotEngraissement;
use App\Models\Mortalite;
use App\Models\Portee;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Tache;
use App\Models\Utilisateur;
use App\Models\Vaccination;
use App\Services\CalendrierVaccinal;
use App\Services\GestionTaches;
use App\Services\PlacementCages;

beforeEach(function () {
    config(['cunicole.taches.quotidiennes' => []]);

    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function produitSante(array $surcharge = []): ProduitVeterinaire
{
    return ProduitVeterinaire::create(array_merge([
        'nom' => 'Produit '.fake()->unique()->lexify('????'),
        'type' => TypeProduit::Vaccin,
        'unite' => 'dose',
        'stock_actuel' => 20,
        'seuil_alerte' => 5,
        'delai_attente_jours' => 0,
    ], $surcharge));
}

function protocoleSante(ProduitVeterinaire $produit, array $surcharge = []): ProtocoleVaccinal
{
    return ProtocoleVaccinal::create(array_merge([
        'nom' => 'Protocole test',
        'produit_id' => $produit->id,
        'cible' => CibleVaccination::Reproducteur,
        'age_premiere_dose_jours' => 0,
        'intervalle_rappel_jours' => 180,
        'doses_par_animal' => 1,
        'actif' => true,
    ], $surcharge));
}

function lotSante(int $effectif = 8): LotEngraissement
{
    $saillie = Saillie::factory()->create([
        'date_saillie' => today()->subDays(150),
        'statut' => StatutSaillie::Sevree,
    ]);

    $portee = Portee::create([
        'saillie_id' => $saillie->id, 'date_mise_bas' => today()->subDays(100),
        'nes_vivants' => $effectif, 'morts_nes' => 0,
        'date_sevrage' => today()->subDays(70), 'sevres' => $effectif,
    ]);

    $cage = Cage::factory()->occupee()->create(['type' => TypeCage::Engraissement, 'capacite' => 20]);

    return LotEngraissement::create([
        'code' => 'LOT-S-'.fake()->unique()->numerify('####'), 'portee_id' => $portee->id, 'cage_id' => $cage->id,
        'effectif_initial' => $effectif, 'effectif_actuel' => $effectif,
        'date_debut' => today()->subDays(70), 'statut' => StatutLot::EnCours,
    ]);
}

function donneesDeces(string $type, int $id, array $surcharge = []): array
{
    return array_merge([
        'cible_type' => $type, 'cible_id' => $id,
        'date_deces' => today()->toDateString(), 'cause' => 'maladie',
    ], $surcharge);
}

// --- Pharmacie ---

it('permet au propriétaire de créer un produit et d\'enregistrer un achat', function () {
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.pharmacie.produits.store'), [
        'nom' => 'Vaccin X', 'type' => 'vaccin', 'unite' => 'dose', 'seuil_alerte' => 5, 'delai_attente_jours' => 0,
    ])->assertSessionHasNoErrors();

    $produit = ProduitVeterinaire::where('nom', 'Vaccin X')->firstOrFail();

    $this->post(route('proprietaire.pharmacie.achats.store', $produit), [
        'quantite' => 30, 'date_achat' => today()->toDateString(), 'fournisseur' => 'Vétérinaire', 'cout_total' => 15000,
    ])->assertSessionHasNoErrors();

    expect((float) $produit->fresh()->stock_actuel)->toBe(30.0);
    $this->assertDatabaseHas('mouvements_pharmacie', ['produit_id' => $produit->id, 'type' => 'achat']);
});

it('refuse un ajustement qui rendrait le stock négatif', function () {
    $produit = produitSante(['stock_actuel' => 5]);
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.pharmacie.ajustements.store', $produit), ['quantite' => -10, 'motif' => 'Casse'])
        ->assertSessionHasErrors('action');

    expect((float) $produit->fresh()->stock_actuel)->toBe(5.0);
});

it('interdit la pharmacie au gestionnaire', function () {
    $this->get(route('proprietaire.pharmacie'))->assertForbidden();
    $this->get(route('proprietaire.protocoles.index'))->assertForbidden();
    $this->get(route('proprietaire.sante'))->assertForbidden();
});

it('crée un protocole du calendrier prophylactique', function () {
    $vaccin = produitSante();
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.protocoles.store'), [
        'nom' => 'Rappel annuel', 'produit_id' => $vaccin->id, 'cible' => 'reproducteur',
        'age_premiere_dose_jours' => 0, 'intervalle_rappel_jours' => 365, 'doses_par_animal' => 1,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('protocoles_vaccinaux', ['nom' => 'Rappel annuel', 'actif' => true]);
});

it("refuse un protocole basé sur un produit qui n'est pas un vaccin", function () {
    $soin = produitSante(['type' => TypeProduit::Traitement]);
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.protocoles.store'), [
        'nom' => 'Erreur', 'produit_id' => $soin->id, 'cible' => 'lot',
        'age_premiere_dose_jours' => 0, 'doses_par_animal' => 1,
    ])->assertSessionHasErrors('produit_id');
});

// --- Calendrier et vaccination ---

it('prévoit la première dose puis le rappel selon le protocole', function () {
    $protocole = protocoleSante(produitSante(), ['age_premiere_dose_jours' => 0, 'intervalle_rappel_jours' => 100]);
    $animal = Reproducteur::factory()->create();
    $calendrier = app(CalendrierVaccinal::class);

    expect($calendrier->echeances())->toHaveCount(1);

    $animal->vaccinations()->create([
        'protocole_id' => $protocole->id, 'nom_vaccin' => 'X', 'date_vaccination' => today()->subDays(50),
    ]);
    expect($calendrier->echeances())->toHaveCount(0);

    $animal->vaccinations()->create([
        'protocole_id' => $protocole->id, 'nom_vaccin' => 'X', 'date_vaccination' => today()->subDays(101),
    ]);
    // La dernière dose reste celle de -50 jours : le rappel n'est pas encore dû
    expect($calendrier->echeances())->toHaveCount(0);

    expect($calendrier->echeances(today()->addDays(60)))->toHaveCount(1);
});

it("n'impose pas la première dose avant l'âge prévu", function () {
    protocoleSante(produitSante(), ['age_premiere_dose_jours' => 400]);
    Reproducteur::factory()->create(['date_naissance' => today()->subMonths(8)->toDateString()]);

    expect(app(CalendrierVaccinal::class)->echeances())->toHaveCount(0);
});

it('génère la tâche de vaccination, la valide en un clic et décompte le stock', function () {
    $produit = produitSante(['stock_actuel' => 10]);
    $protocole = protocoleSante($produit);
    $animal = Reproducteur::factory()->create();

    $gestion = app(GestionTaches::class);
    $gestion->synchroniser();

    $tache = Tache::where('type', 'vaccination')->firstOrFail();
    expect($tache->protocole_id)->toBe($protocole->id);

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasNoErrors();

    expect((float) $produit->fresh()->stock_actuel)->toBe(9.0);
    expect($tache->fresh()->statut)->toBe(StatutTache::Faite);
    expect($tache->fresh()->faite_par_id)->toBe($this->gestionnaire->id);
    expect($animal->vaccinations()->count())->toBe(1);
    $this->assertDatabaseHas('mouvements_pharmacie', ['produit_id' => $produit->id, 'type' => 'utilisation']);

    // Plus de rappel tant que l'intervalle n'est pas écoulé
    expect($gestion->synchroniser())->toBe(0);
});

it('refuse la vaccination quand le stock est insuffisant', function () {
    $produit = produitSante(['stock_actuel' => 0]);
    protocoleSante($produit);
    Reproducteur::factory()->create();

    app(GestionTaches::class)->synchroniser();
    $tache = Tache::where('type', 'vaccination')->firstOrFail();

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasErrors('action');

    expect(Vaccination::count())->toBe(0);
    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
});

it("décompte autant de doses que d'animaux pour un lot", function () {
    $produit = produitSante(['stock_actuel' => 20]);
    protocoleSante($produit, ['cible' => CibleVaccination::Lot, 'age_premiere_dose_jours' => 30, 'intervalle_rappel_jours' => null]);
    $lot = lotSante(8);

    app(GestionTaches::class)->synchroniser();
    $tache = Tache::where('type', 'vaccination')->where('source_type', 'lot')->firstOrFail();

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasNoErrors();

    expect((float) $produit->fresh()->stock_actuel)->toBe(12.0);
    expect($lot->vaccinations()->count())->toBe(1);
});

it("enregistre une vaccination antérieure sans toucher au stock", function () {
    $produit = produitSante(['stock_actuel' => 10]);
    $protocole = protocoleSante($produit);
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.vaccination_anterieure'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id,
        'protocole_id' => $protocole->id, 'date_vaccination' => today()->subDays(20)->toDateString(),
    ])->assertSessionHasNoErrors();

    expect((float) $produit->fresh()->stock_actuel)->toBe(10.0);
    expect(app(CalendrierVaccinal::class)->echeances())->toHaveCount(0);
    $this->assertDatabaseHas('vaccinations', ['vaccinable_id' => $animal->id, 'anterieure' => true]);
});

// --- Traitements ---

it('enregistre un traitement, décompte le stock et bloque la vente', function () {
    $soin = produitSante(['type' => TypeProduit::Traitement, 'unite' => 'ml', 'stock_actuel' => 50]);
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.traiter'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'produit_id' => $soin->id,
        'symptomes' => 'Éternuements', 'date_debut' => today()->toDateString(),
        'duree_jours' => 3, 'delai_attente_jours' => 7, 'quantite' => 2,
    ])->assertSessionHasNoErrors();

    $traitement = $animal->traitements()->firstOrFail();

    expect($traitement->date_fin_traitement->toDateString())->toBe(today()->addDays(2)->toDateString());
    expect($traitement->date_fin_attente->toDateString())->toBe(today()->addDays(9)->toDateString());
    expect($animal->estBloquePourVente())->toBeTrue();
    expect((float) $soin->fresh()->stock_actuel)->toBe(48.0);
});

it('exige une quantité quand un produit de la pharmacie est choisi', function () {
    $soin = produitSante(['type' => TypeProduit::Traitement]);
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.traiter'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'produit_id' => $soin->id,
        'symptomes' => 'Toux', 'date_debut' => today()->toDateString(),
        'duree_jours' => 2, 'delai_attente_jours' => 0,
    ])->assertSessionHasErrors('quantite');
});

it('exige un nom de traitement sans produit de la pharmacie', function () {
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.traiter'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id,
        'symptomes' => 'Toux', 'date_debut' => today()->toDateString(),
        'duree_jours' => 2, 'delai_attente_jours' => 0,
    ])->assertSessionHasErrors('nom_traitement');
});

it("ne bloque plus la vente une fois le délai d'attente écoulé", function () {
    $animal = Reproducteur::factory()->create();

    $animal->traitements()->create([
        'nom_traitement' => 'Ancien', 'symptomes' => 'Test',
        'date_debut' => today()->subDays(20), 'duree_jours' => 3, 'delai_attente_jours' => 7,
        'date_fin_traitement' => today()->subDays(18), 'date_fin_attente' => today()->subDays(11),
    ]);

    expect($animal->estBloquePourVente())->toBeFalse();
});

it('bloque tout un lot traité', function () {
    $lot = lotSante(6);

    $this->post(route('gestionnaire.sante.traiter'), [
        'cible_type' => 'lot', 'cible_id' => $lot->id, 'nom_traitement' => 'Soin collectif',
        'symptomes' => 'Diarrhée', 'date_debut' => today()->toDateString(),
        'duree_jours' => 2, 'delai_attente_jours' => 5,
    ])->assertSessionHasNoErrors();

    expect($lot->estBloquePourVente())->toBeTrue();
});

it('génère une tâche quotidienne pendant la durée du traitement', function () {
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.traiter'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'nom_traitement' => 'Soin',
        'symptomes' => 'Toux', 'date_debut' => today()->toDateString(),
        'duree_jours' => 3, 'delai_attente_jours' => 0,
    ]);

    app(GestionTaches::class)->synchroniser();

    expect(Tache::where('type', 'traitement')->count())->toBe(1);

    $tache = Tache::where('type', 'traitement')->firstOrFail();
    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasNoErrors();
    expect($tache->fresh()->statut)->toBe(StatutTache::Faite);
});

// --- Mortalités ---

it("déclare le décès d'un reproducteur, libère sa cage et interrompt sa saillie", function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $femelle = Reproducteur::factory()->create();
    app(PlacementCages::class)->placer($femelle, $cage);
    $saillie = Saillie::factory()->create([
        'femelle_id' => $femelle->id, 'statut' => StatutSaillie::EnAttente, 'date_saillie' => today()->subDays(3),
    ]);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('reproducteur', $femelle->id, ['cause' => 'digestive']))
        ->assertSessionHasNoErrors();

    expect($femelle->fresh()->statut)->toBe(StatutReproducteur::Mort);
    expect($femelle->fresh()->cage_id)->toBeNull();
    expect($cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
    expect($saillie->fresh()->statut)->toBe(StatutSaillie::Interrompue);
    $this->assertDatabaseHas('mortalites', ['reproducteur_id' => $femelle->id, 'cause' => 'digestive', 'nombre' => 1]);
});

it('exige la cause suspectée du décès', function () {
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.sante.deces'), [
        'cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'date_deces' => today()->toDateString(),
    ])->assertSessionHasErrors('cause');

    expect($animal->fresh()->estActif())->toBeTrue();
});

it("refuse le décès d'un animal déjà sorti du cheptel", function () {
    $animal = Reproducteur::factory()->create(['statut' => StatutReproducteur::Vendu]);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('reproducteur', $animal->id))
        ->assertSessionHasErrors('action');
});

it("annule la vaccination prévue d'un animal décédé", function () {
    protocoleSante(produitSante());
    $animal = Reproducteur::factory()->create();
    app(GestionTaches::class)->synchroniser();

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('reproducteur', $animal->id));

    expect(Tache::where('type', 'vaccination')->firstOrFail()->statut)->toBe(StatutTache::Annulee);
});

it("déduit les décès de l'effectif du lot et le termine quand il est vide", function () {
    $lot = lotSante(3);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 2]))
        ->assertSessionHasNoErrors();
    expect($lot->fresh()->effectif_actuel)->toBe(1);
    expect($lot->fresh()->statut)->toBe(StatutLot::EnCours);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 1]))
        ->assertSessionHasNoErrors();
    expect($lot->fresh()->statut)->toBe(StatutLot::Termine);
    expect($lot->cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
});

it("refuse plus de décès que l'effectif du lot", function () {
    $lot = lotSante(3);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 5]))
        ->assertSessionHasErrors('nombre');

    expect($lot->fresh()->effectif_actuel)->toBe(3);
});

it('réduit le nombre de sevrables après des décès de lapereaux', function () {
    $saillie = Saillie::factory()->create(['statut' => StatutSaillie::MiseBas, 'date_saillie' => today()->subDays(62)]);
    $portee = Portee::create([
        'saillie_id' => $saillie->id, 'date_mise_bas' => today()->subDays(31), 'nes_vivants' => 8, 'morts_nes' => 0,
    ]);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Engraissement, 'capacite' => 10]);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('portee', $portee->id, ['nombre' => 3]))
        ->assertSessionHasNoErrors();

    expect($portee->fresh()->morts_avant_sevrage)->toBe(3);

    $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
        'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 6, 'cage_id' => $cage->id,
    ])->assertSessionHasErrors('nombre_sevres');

    $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
        'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 5, 'cage_id' => $cage->id,
    ])->assertSessionHasNoErrors();
});

it("alerte le propriétaire quand le taux dépasse le seuil, une seule fois", function () {
    // 2 reproducteurs créés par la saillie + 18 lapins = 20 animaux vivants au départ
    $lot = lotSante(18);

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 1]));
    expect($this->proprietaire->notifications()->count())->toBe(0);   // 1/20 = 5 %, pas strictement supérieur

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 1]));
    expect($this->proprietaire->notifications()->count())->toBe(1);   // 2/20 = 10 %

    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 1]));
    expect($this->proprietaire->notifications()->count())->toBe(1);   // pas de doublon sous 24 h

    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.tableau_de_bord'))
        ->assertOk()
        ->assertSee('URGENT');
});

it('calcule le taux de mortalité sur la période', function () {
    $lot = lotSante(18);
    $this->post(route('gestionnaire.sante.deces'), donneesDeces('lot', $lot->id, ['nombre' => 1]));

    $etat = app(\App\Services\SuiviMortalite::class)->taux();

    expect($etat['deces'])->toBe(1);
    expect($etat['taux'])->toBe(5.0);
    expect($etat['depasse'])->toBeFalse();
});

// --- Pages ---

it('affiche les écrans de santé au gestionnaire', function () {
    $this->get(route('gestionnaire.sante.index'))->assertOk();
    $this->get(route('gestionnaire.plus'))->assertOk();

    $animal = Reproducteur::factory()->create();
    $this->get(route('gestionnaire.reproducteurs.show', $animal))->assertOk()->assertSee('Déclarer un décès');
});

it('affiche les écrans de santé au propriétaire', function () {
    produitSante();
    Mortalite::create([
        'cible' => 'lot', 'code_cible' => 'LOT-X', 'nombre' => 1, 'date_deces' => today(), 'cause' => 'inconnue',
    ]);

    $this->actingAs($this->proprietaire);

    $this->get(route('proprietaire.sante'))->assertOk();
    $this->get(route('proprietaire.pharmacie'))->assertOk();
    $this->get(route('proprietaire.protocoles.index'))->assertOk();
});