<?php

use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutPaiement;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\TypeCage;
use App\Models\Aliment;
use App\Models\Cage;
use App\Models\Charge;
use App\Models\Client;
use App\Models\LotEngraissement;
use App\Models\MouvementAliment;
use App\Models\MouvementPharmacie;
use App\Models\ParametreVente;
use App\Models\ProduitVeterinaire;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Tarif;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\BilanFinancier;
use App\Services\PlacementCages;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function tarifVenteTest(string $categorie, string $mode, float $prix): Tarif
{
    return Tarif::create(['categorie' => $categorie, 'mode' => $mode, 'prix' => $prix, 'actif' => true]);
}

function lotAVendre(int $effectif = 10): LotEngraissement
{
    $cage = Cage::factory()->occupee()->create(['type' => TypeCage::Engraissement, 'capacite' => 20]);

    return LotEngraissement::create([
        'code' => 'LOT-V-'.fake()->unique()->numerify('####'),
        'cage_id' => $cage->id,
        'effectif_initial' => $effectif,
        'effectif_actuel' => $effectif,
        'date_debut' => today()->subDays(60),
        'statut' => StatutLot::EnCours,
    ]);
}

function donneesVente(array $lignes, array $surcharge = []): array
{
    return array_merge([
        'jeton' => (string) Str::uuid(),
        'lignes' => $lignes,
        'remise_pct' => 0,
        'montant_paye' => 0,
        'mode_paiement' => 'especes',
    ], $surcharge);
}

function venteDirecte(float $net, float $paye = 0, string $numero = 'V-TEST-0001'): Vente
{
    return Vente::create([
        'numero' => $numero, 'date_vente' => today(),
        'total_brut' => $net, 'remise_pct' => 0, 'remise_montant' => 0,
        'total_net' => $net, 'montant_paye' => $paye,
    ]);
}

// --- Vente de lapins d'un lot ---

it('refuse une vente sans tarif fixé', function () {
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente([['type' => 'lot', 'id' => $lot->id, 'quantite' => 2, 'poids_kg' => 5]]))
        ->assertSessionHasErrors('lignes.0.id');

    expect(Vente::count())->toBe(0);
    expect($lot->fresh()->effectif_actuel)->toBe(10);
});

it('vend des lapins au kg, décompte le lot et enregistre le paiement', function () {
    tarifVenteTest('engraissement', 'kg', 2500);
    $lot = lotAVendre(10);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 3, 'poids_kg' => 9.5]],
        ['montant_paye' => 23750],
    ))->assertSessionHasNoErrors();

    $vente = Vente::with(['lignes', 'paiements'])->firstOrFail();

    expect($vente->numero)->toBe('V-'.today()->year.'-0001');
    expect((float) $vente->total_net)->toBe(23750.0);
    expect($vente->statutPaiement())->toBe(StatutPaiement::Payee);
    expect($vente->lignes)->toHaveCount(1);
    expect($vente->lignes->first()->quantite)->toBe(3);
    expect($vente->paiements)->toHaveCount(1);
    expect($lot->fresh()->effectif_actuel)->toBe(7);
});

it('vend à la pièce avec une remise autorisée', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    ParametreVente::actuel()->update(['remise_max_pct' => 10]);
    $lot = lotAVendre(10);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['remise_pct' => 10, 'montant_paye' => 9000],
    ))->assertSessionHasNoErrors();

    $vente = Vente::firstOrFail();

    expect((float) $vente->total_brut)->toBe(10000.0);
    expect((float) $vente->remise_montant)->toBe(1000.0);
    expect((float) $vente->total_net)->toBe(9000.0);
});

it('refuse une remise supérieure au maximum autorisé', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    ParametreVente::actuel()->update(['remise_max_pct' => 10]);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['remise_pct' => 15, 'montant_paye' => 8500],
    ))->assertSessionHasErrors('remise_pct');

    expect(Vente::count())->toBe(0);
});

it("refuse toute remise tant que le propriétaire n'en autorise pas", function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['remise_pct' => 5, 'montant_paye' => 9500],
    ))->assertSessionHasErrors('remise_pct');
});

it("termine le lot vendu en totalité et passe sa cage « à nettoyer »", function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre(3);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 3]],
        ['montant_paye' => 15000],
    ))->assertSessionHasNoErrors();

    expect($lot->fresh()->statut)->toBe(StatutLot::Termine);
    expect($lot->fresh()->effectif_actuel)->toBe(0);
    expect($lot->cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
});

it("refuse de vendre plus de lapins que l'effectif du lot", function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre(3);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 5]],
        ['montant_paye' => 25000],
    ))->assertSessionHasErrors('lignes.0.quantite');

    expect($lot->fresh()->effectif_actuel)->toBe(3);
});

it('exige le poids quand le tarif est au kg', function () {
    tarifVenteTest('engraissement', 'kg', 2500);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['montant_paye' => 5000],
    ))->assertSessionHasErrors('lignes.0.poids_kg');
});

it('refuse un lot bloqué pour la vente par un traitement', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();

    $lot->traitements()->create([
        'nom_traitement' => 'Soin collectif', 'symptomes' => 'Diarrhée',
        'date_debut' => today()->subDay(), 'duree_jours' => 3, 'delai_attente_jours' => 7,
        'date_fin_traitement' => today()->addDay(), 'date_fin_attente' => today()->addDays(8),
    ]);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['montant_paye' => 10000],
    ))->assertSessionHasErrors('lignes.0.id');

    expect($lot->fresh()->effectif_actuel)->toBe(10);
});

it('refuse deux lignes pour le même lot', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [
            ['type' => 'lot', 'id' => $lot->id, 'quantite' => 2],
            ['type' => 'lot', 'id' => $lot->id, 'quantite' => 2],
        ],
        ['montant_paye' => 20000],
    ))->assertSessionHasErrors('lignes.1.id');
});

// --- Vente de reproducteurs ---

it("vend un reproducteur : il est « Vendu », sa cage est libérée et sa saillie interrompue", function () {
    tarifVenteTest('reproducteur', 'unite', 15000);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $animal = Reproducteur::factory()->create();
    app(PlacementCages::class)->placer($animal, $cage);
    $saillie = Saillie::factory()->create([
        'femelle_id' => $animal->id, 'statut' => StatutSaillie::EnAttente, 'date_saillie' => today()->subDays(3),
    ]);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'reproducteur', 'id' => $animal->id]],
        ['montant_paye' => 15000],
    ))->assertSessionHasNoErrors();

    expect($animal->fresh()->statut)->toBe(StatutReproducteur::Vendu);
    expect($animal->fresh()->cage_id)->toBeNull();
    expect($cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
    expect($saillie->fresh()->statut)->toBe(StatutSaillie::Interrompue);
});

it('refuse un reproducteur non encore validé par le propriétaire', function () {
    tarifVenteTest('reproducteur', 'unite', 15000);
    $animal = Reproducteur::factory()->create(['valide_le' => null]);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'reproducteur', 'id' => $animal->id]],
        ['montant_paye' => 15000],
    ))->assertSessionHasErrors('lignes.0.id');

    expect($animal->fresh()->estActif())->toBeTrue();
});

it('refuse une femelle qui allaite une portée', function () {
    tarifVenteTest('reproducteur', 'unite', 15000);
    $animal = Reproducteur::factory()->create();
    Saillie::factory()->create(['femelle_id' => $animal->id, 'statut' => StatutSaillie::MiseBas, 'date_saillie' => today()->subDays(40)]);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'reproducteur', 'id' => $animal->id]],
        ['montant_paye' => 15000],
    ))->assertSessionHasErrors('lignes.0.id');
});

it('vend un animal réformé au tarif des réformés', function () {
    tarifVenteTest('reforme', 'kg', 2000);
    $animal = Reproducteur::factory()->create(['statut' => StatutReproducteur::Reforme, 'date_sortie' => today()->subDays(2)]);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'reproducteur', 'id' => $animal->id, 'poids_kg' => 3]],
        ['montant_paye' => 6000],
    ))->assertSessionHasNoErrors();

    expect($animal->fresh()->statut)->toBe(StatutReproducteur::Vendu);
    expect((float) Vente::firstOrFail()->total_net)->toBe(6000.0);
});

it("n'autorise plus la sortie « vendu » depuis la fiche de l'animal", function () {
    $animal = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.sortie', $animal), [
        'statut' => 'vendu', 'date_sortie' => today()->toDateString(),
    ])->assertSessionHasErrors('statut');

    expect($animal->fresh()->estActif())->toBeTrue();
});

// --- Crédit, clients et paiements ---

it('refuse une vente à crédit tant que le crédit est interdit', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();
    $client = Client::create(['nom' => 'Client Test']);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['client_id' => $client->id, 'montant_paye' => 4000],
    ))->assertSessionHasErrors('montant_paye');
});

it('exige un client enregistré pour une vente à crédit', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    ParametreVente::actuel()->update(['credit_autorise' => true]);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['montant_paye' => 4000],
    ))->assertSessionHasErrors('client_id');
});

it('enregistre une vente à crédit puis encaisse les soldes', function () {
    tarifVenteTest('engraissement', 'kg', 2500);
    ParametreVente::actuel()->update(['credit_autorise' => true]);
    $lot = lotAVendre();
    $client = Client::create(['nom' => 'Client Test']);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 3, 'poids_kg' => 8]],
        ['client_id' => $client->id, 'montant_paye' => 5000],
    ))->assertSessionHasNoErrors();

    $vente = Vente::firstOrFail();
    expect($vente->solde())->toBe(15000.0);
    expect($vente->statutPaiement())->toBe(StatutPaiement::Partielle);

    $this->post(route('gestionnaire.ventes.paiements.store', $vente), ['montant' => 20000, 'mode_paiement' => 'especes'])
        ->assertSessionHasErrors('montant');

    $this->post(route('gestionnaire.ventes.paiements.store', $vente), [
        'montant' => 10000, 'mode_paiement' => 'mobile_money', 'operateur' => 'Opérateur Test', 'reference' => 'TX123',
    ])->assertSessionHasNoErrors();

    $this->post(route('gestionnaire.ventes.paiements.store', $vente), ['montant' => 5000, 'mode_paiement' => 'especes'])
        ->assertSessionHasNoErrors();

    expect($vente->fresh()->solde())->toBe(0.0);
    expect($vente->fresh()->statutPaiement())->toBe(StatutPaiement::Payee);

    $this->post(route('gestionnaire.ventes.paiements.store', $vente), ['montant' => 1000, 'mode_paiement' => 'especes'])
        ->assertSessionHasErrors('montant');
});

it('refuse un paiement supérieur au total', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['montant_paye' => 20000],
    ))->assertSessionHasErrors('montant_paye');
});

it('crée un nouveau client pendant la vente', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['nouveau_client_nom' => 'Nouveau Client', 'nouveau_client_telephone' => '97000000', 'montant_paye' => 10000],
    ))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('clients', ['nom' => 'Nouveau Client']);
    expect(Vente::firstOrFail()->client?->nom)->toBe('Nouveau Client');
});

it('refuse à la fois un client existant et un nouveau client', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre();
    $client = Client::create(['nom' => 'Client Test']);

    $this->post(route('gestionnaire.ventes.store'), donneesVente(
        [['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]],
        ['client_id' => $client->id, 'nouveau_client_nom' => 'Autre', 'montant_paye' => 10000],
    ))->assertSessionHasErrors('nouveau_client_nom');
});

// --- Sécurité de l'enregistrement ---

it('ne crée pas deux ventes quand le formulaire est envoyé deux fois', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre(10);
    $donnees = donneesVente([['type' => 'lot', 'id' => $lot->id, 'quantite' => 2]], ['montant_paye' => 10000]);

    $this->post(route('gestionnaire.ventes.store'), $donnees)->assertSessionHasNoErrors();
    $this->post(route('gestionnaire.ventes.store'), $donnees)->assertSessionHasNoErrors();

    expect(Vente::count())->toBe(1);
    expect($lot->fresh()->effectif_actuel)->toBe(8);
});

it('numérote les ventes à la suite', function () {
    tarifVenteTest('engraissement', 'unite', 5000);
    $lot = lotAVendre(10);

    foreach ([1, 2] as $_) {
        $this->post(route('gestionnaire.ventes.store'), donneesVente(
            [['type' => 'lot', 'id' => $lot->id, 'quantite' => 1]],
            ['montant_paye' => 5000],
        ));
    }

    expect(Vente::orderBy('id')->pluck('numero')->all())->toBe([
        'V-'.today()->year.'-0001',
        'V-'.today()->year.'-0002',
    ]);
});

// --- Reçus ---

it('génère le reçu PDF pour le gestionnaire et pour le propriétaire', function () {
    $vente = venteDirecte(10000, 10000);

    $this->get(route('gestionnaire.ventes.recu', $vente))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.ventes.recu', $vente))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

// --- Dépenses ---

it("enregistre une dépense courante du gestionnaire", function () {
    $this->post(route('gestionnaire.depenses.store'), [
        'categorie' => 'litiere', 'libelle' => 'Paille 2 bottes', 'montant' => 5000, 'date_charge' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('charges', ['categorie' => 'litiere', 'montant' => 5000, 'utilisateur_id' => $this->gestionnaire->id]);
});

it('interdit au gestionnaire de saisir une charge fixe', function () {
    $this->post(route('gestionnaire.depenses.store'), [
        'categorie' => 'salaires', 'libelle' => 'Salaire', 'montant' => 40000, 'date_charge' => today()->toDateString(),
    ])->assertSessionHasErrors('categorie');

    expect(Charge::count())->toBe(0);
});

it('permet au gestionnaire d\'annuler sa propre dépense du jour seulement', function () {
    $propre = Charge::create(['categorie' => 'litiere', 'libelle' => 'Paille', 'montant' => 1000, 'date_charge' => today(), 'utilisateur_id' => $this->gestionnaire->id]);
    $autre = Charge::create(['categorie' => 'litiere', 'libelle' => 'Paille', 'montant' => 1000, 'date_charge' => today(), 'utilisateur_id' => Utilisateur::factory()->create()->id]);

    $this->delete(route('gestionnaire.depenses.destroy', $propre))->assertSessionHasNoErrors();
    $this->delete(route('gestionnaire.depenses.destroy', $autre))->assertSessionHasErrors('action');

    $this->assertDatabaseMissing('charges', ['id' => $propre->id]);
    $this->assertDatabaseHas('charges', ['id' => $autre->id]);
});

it('permet au propriétaire de saisir et supprimer une charge fixe', function () {
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.charges.store'), [
        'categorie' => 'loyer', 'libelle' => 'Loyer du mois', 'montant' => 25000, 'date_charge' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $charge = Charge::firstOrFail();

    $this->delete(route('proprietaire.charges.destroy', $charge))->assertSessionHasNoErrors();
    expect(Charge::count())->toBe(0);
});

// --- Bilan financier ---

it('calcule le bénéfice net : chiffre d\'affaires moins charges (dépenses, aliments, pharmacie)', function () {
    venteDirecte(100000, 60000);

    Charge::create(['categorie' => 'salaires', 'libelle' => 'Salaire', 'montant' => 30000, 'date_charge' => today()]);
    Charge::create(['categorie' => 'litiere', 'libelle' => 'Paille', 'montant' => 5000, 'date_charge' => today()]);

    $aliment = Aliment::create(['nom' => 'Aliment Bilan', 'stock_kg' => 0]);
    MouvementAliment::create([
        'aliment_id' => $aliment->id, 'type' => 'reception', 'quantite_kg' => 100,
        'date_mouvement' => today(), 'cout_total' => 20000,
    ]);

    $produit = ProduitVeterinaire::create(['nom' => 'Produit Bilan', 'type' => 'vaccin', 'unite' => 'dose']);
    MouvementPharmacie::create([
        'produit_id' => $produit->id, 'type' => 'achat', 'quantite' => 10,
        'date_mouvement' => today(), 'cout_total' => 8000,
    ]);

    $bilan = app(BilanFinancier::class)->periode(today()->startOfMonth(), today()->endOfMonth());

    expect($bilan['ca'])->toBe(100000.0);
    expect($bilan['charges']['total'])->toBe(63000.0);
    expect($bilan['benefice'])->toBe(37000.0);
    expect($bilan['marge'])->toBe(37.0);
    expect(app(BilanFinancier::class)->creances())->toBe(40000.0);
});

it('signale les achats sans montant et calcule la progression des objectifs', function () {
    venteDirecte(30000, 30000);
    ParametreVente::actuel()->update(['objectif_hebdomadaire' => 60000, 'objectif_mensuel' => 120000]);

    $aliment = Aliment::create(['nom' => 'Aliment Sans Prix', 'stock_kg' => 0]);
    MouvementAliment::create(['aliment_id' => $aliment->id, 'type' => 'reception', 'quantite_kg' => 50, 'date_mouvement' => today()]);

    $bilan = app(BilanFinancier::class);

    expect($bilan->charges(today()->startOfMonth(), today()->endOfMonth())['sans_montant'])->toBe(1);

    $objectifs = $bilan->progressionObjectifs();
    expect($objectifs['semaine']['pourcentage'])->toBe(50);
    expect($objectifs['mois']['pourcentage'])->toBe(25);
});

// --- Conditions de vente et pages ---

it('permet au propriétaire de définir la grille tarifaire et les conditions', function () {
    $this->actingAs($this->proprietaire);

    $this->put(route('proprietaire.tarifs.update', 'engraissement'), ['mode' => 'kg', 'prix' => 2600, 'actif' => 1])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tarifs', ['categorie' => 'engraissement', 'mode' => 'kg', 'prix' => 2600]);

    $this->put(route('proprietaire.tarifs.update', 'engraissement'), ['mode' => 'unite', 'prix' => 4000])
        ->assertSessionHasNoErrors();
    expect(Tarif::count())->toBe(1);

    $this->put(route('proprietaire.tarifs.parametres'), [
        'remise_max_pct' => 8, 'credit_autorise' => 1, 'objectif_hebdomadaire' => 50000, 'objectif_mensuel' => 200000,
    ])->assertSessionHasNoErrors();

    $parametres = ParametreVente::actuel();
    expect((float) $parametres->remise_max_pct)->toBe(8.0);
    expect($parametres->credit_autorise)->toBeTrue();
});

it('affiche les écrans du gestionnaire', function () {
    tarifVenteTest('engraissement', 'kg', 2500);
    lotAVendre();
    $vente = venteDirecte(10000, 5000);

    $this->get(route('gestionnaire.ventes.index'))->assertOk()->assertSee('Soldes à encaisser');
    $this->get(route('gestionnaire.ventes.create'))->assertOk()->assertSee('Nouvelle vente');
    $this->get(route('gestionnaire.ventes.show', $vente))->assertOk()->assertSee($vente->numero);
    $this->get(route('gestionnaire.depenses.index'))->assertOk();
    $this->get(route('gestionnaire.plus'))->assertOk()->assertSee('Ventes');
});

it('affiche les écrans financiers du propriétaire', function () {
    $vente = venteDirecte(10000, 5000);
    $this->actingAs($this->proprietaire);

    $this->get(route('proprietaire.finances'))->assertOk()->assertSee('Bénéfice net');
    $this->get(route('proprietaire.finances', ['periode' => 'annee']))->assertOk();
    $this->get(route('proprietaire.ventes.index'))->assertOk()->assertSee($vente->numero);
    $this->get(route('proprietaire.ventes.index', ['statut' => 'impayees']))->assertOk();
    $this->get(route('proprietaire.ventes.show', $vente))->assertOk();
    $this->get(route('proprietaire.charges.index'))->assertOk();
    $this->get(route('proprietaire.tarifs'))->assertOk();
});

it('sépare les accès du gestionnaire et du propriétaire', function () {
    $this->get(route('proprietaire.finances'))->assertForbidden();
    $this->get(route('proprietaire.tarifs'))->assertForbidden();
    $this->get(route('proprietaire.charges.index'))->assertForbidden();

    $this->actingAs($this->proprietaire);

    $this->get(route('gestionnaire.ventes.index'))->assertForbidden();
    $this->get(route('gestionnaire.depenses.index'))->assertForbidden();
});