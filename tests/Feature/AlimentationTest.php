<?php

use App\Enums\CategorieAlimentation;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\StatutTache;
use App\Models\Aliment;
use App\Models\ClotureJournee;
use App\Models\Distribution;
use App\Models\LotEngraissement;
use App\Models\Ration;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Tache;
use App\Models\Utilisateur;
use App\Notifications\AlerteStockAliment;
use App\Services\EffectifsAlimentation;
use App\Services\GestionAliments;
use App\Services\GestionTaches;

beforeEach(function () {
    config(['cunicole.taches.quotidiennes' => []]);

    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function alimentTest(array $surcharge = []): Aliment
{
    return Aliment::create(array_merge([
        'nom' => 'Aliment '.fake()->unique()->lexify('????'),
        'poids_sac_kg' => 25,
        'stock_kg' => 100,
        'actif' => true,
    ], $surcharge));
}

function rationTest(Aliment $aliment, CategorieAlimentation $categorie, int $grammes = 100, array $surcharge = []): Ration
{
    return Ration::create(array_merge([
        'categorie' => $categorie,
        'aliment_id' => $aliment->id,
        'grammes_par_jour' => $grammes,
        'actif' => true,
    ], $surcharge));
}

function lotAlimentation(int $effectif): LotEngraissement
{
    return LotEngraissement::create([
        'code' => 'LOT-A-'.fake()->unique()->numerify('####'),
        'effectif_initial' => $effectif,
        'effectif_actuel' => $effectif,
        'date_debut' => today()->subDays(40),
        'statut' => StatutLot::EnCours,
    ]);
}

// --- Effectifs ---

it('compte les animaux par catégorie alimentaire', function () {
    $male = Reproducteur::factory()->male()->create();
    $gestante = Reproducteur::factory()->create();
    $allaitante = Reproducteur::factory()->create();
    $enAttente = Reproducteur::factory()->create();
    Reproducteur::factory()->create();   // au repos, sans saillie
    $morte = Reproducteur::factory()->create(['statut' => StatutReproducteur::Mort]);

    foreach ([
        [$gestante, StatutSaillie::Gestante],
        [$allaitante, StatutSaillie::MiseBas],
        [$enAttente, StatutSaillie::EnAttente],
        [$morte, StatutSaillie::Gestante],
    ] as [$femelle, $statut]) {
        Saillie::factory()->create(['femelle_id' => $femelle->id, 'male_id' => $male->id, 'statut' => $statut]);
    }

    lotAlimentation(8);

    expect(app(EffectifsAlimentation::class)->parCategorie())->toEqual([
        'male' => 1,
        'femelle_vide' => 2,
        'femelle_gestante' => 1,
        'femelle_allaitante' => 1,
        'engraissement' => 8,
    ]);
});

// --- Réception ---

it('enregistre la réception de sacs et met le stock à jour', function () {
    $aliment = alimentTest(['stock_kg' => 10]);

    $this->post(route('gestionnaire.alimentation.recevoir'), [
        'aliment_id' => $aliment->id, 'nombre_sacs' => 4, 'poids_sac_kg' => 25,
        'date_reception' => today()->toDateString(), 'fournisseur' => 'Fournisseur Test', 'cout_total' => 40000,
    ])->assertSessionHasNoErrors();

    expect((float) $aliment->fresh()->stock_kg)->toBe(110.0);
    $this->assertDatabaseHas('mouvements_aliments', ['aliment_id' => $aliment->id, 'type' => 'reception', 'nombre_sacs' => 4]);
});

it('refuse une réception invalide', function () {
    $aliment = alimentTest();

    $this->post(route('gestionnaire.alimentation.recevoir'), [
        'aliment_id' => $aliment->id, 'nombre_sacs' => 0, 'poids_sac_kg' => 25, 'date_reception' => today()->toDateString(),
    ])->assertSessionHasErrors('nombre_sacs');

    $inactif = alimentTest(['actif' => false]);

    $this->post(route('gestionnaire.alimentation.recevoir'), [
        'aliment_id' => $inactif->id, 'nombre_sacs' => 2, 'poids_sac_kg' => 25, 'date_reception' => today()->toDateString(),
    ])->assertSessionHasErrors('aliment_id');
});

// --- Distribution ---

it('enregistre une distribution, décompte le stock et termine la tâche', function () {
    $aliment = alimentTest(['stock_kg' => 50]);
    $ration = rationTest($aliment, CategorieAlimentation::Male, 150);
    Reproducteur::factory()->male()->count(2)->create();

    app(GestionTaches::class)->synchroniser();

    $tache = Tache::where('type', 'distribution')->firstOrFail();
    expect($tache->source_id)->toBe($ration->id);
    expect($tache->statut)->toBe(StatutTache::AFaire);

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 0.31])
        ->assertSessionHasNoErrors();

    $distribution = Distribution::firstOrFail();
    expect($distribution->nombre_animaux)->toBe(2);
    expect((float) $distribution->quantite_theorique_kg)->toBe(0.3);
    expect((float) $distribution->quantite_servie_kg)->toBe(0.31);
    expect((float) $aliment->fresh()->stock_kg)->toBe(49.69);

    $tache->refresh();
    expect($tache->statut)->toBe(StatutTache::Faite);
    expect($tache->faite_par_id)->toBe($this->gestionnaire->id);
    expect($tache->faite_le)->not->toBeNull();
});

it('refuse deux distributions de la même ration le même jour', function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::Male, 100);
    Reproducteur::factory()->male()->create();

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 0.1])
        ->assertSessionHasNoErrors();

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 0.1])
        ->assertSessionHasErrors('action');

    expect(Distribution::count())->toBe(1);
});

it('refuse une distribution quand le stock est insuffisant', function () {
    $aliment = alimentTest(['stock_kg' => 0.1]);
    $ration = rationTest($aliment, CategorieAlimentation::Male, 150);
    Reproducteur::factory()->male()->create();

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 0.5])
        ->assertSessionHasErrors('action');

    expect(Distribution::count())->toBe(0);
    expect((float) $aliment->fresh()->stock_kg)->toBe(0.1);
});

it('refuse une quantité servie nulle', function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::Male);
    Reproducteur::factory()->male()->create();

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 0])
        ->assertSessionHasErrors('quantite_servie_kg');
});

it("ne génère pas de tâche et refuse la distribution pour une catégorie sans animal", function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::FemelleGestante, 200);

    app(GestionTaches::class)->synchroniser();
    expect(Tache::where('type', 'distribution')->count())->toBe(0);

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 1])
        ->assertSessionHasErrors('action');
});

it('ignore les rations désactivées', function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::Male, 100, ['actif' => false]);
    Reproducteur::factory()->male()->create();

    app(GestionTaches::class)->synchroniser();
    expect(Tache::where('type', 'distribution')->count())->toBe(0);

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 1])
        ->assertSessionHasErrors('action');
});

it('refuse la distribution quand la journée est clôturée', function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::Male, 100);
    Reproducteur::factory()->male()->create();

    ClotureJournee::create([
        'date' => today(), 'utilisateur_id' => $this->gestionnaire->id, 'cloturee_le' => now(),
        'total' => 0, 'faites' => 0, 'non_faites' => 0,
    ]);

    $this->post(route('gestionnaire.alimentation.distribuer', $ration), ['quantite_servie_kg' => 1])
        ->assertSessionHasErrors('action');
});

it('ne permet pas de cocher à la main une tâche de distribution', function () {
    rationTest(alimentTest(), CategorieAlimentation::Male);
    Reproducteur::factory()->male()->create();
    app(GestionTaches::class)->synchroniser();

    $tache = Tache::where('type', 'distribution')->firstOrFail();

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasErrors('action');
    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
});

it('annule la tâche de distribution quand la ration est désactivée', function () {
    $ration = rationTest(alimentTest(), CategorieAlimentation::Male, 100);
    Reproducteur::factory()->male()->create();
    app(GestionTaches::class)->synchroniser();

    $this->actingAs($this->proprietaire)
        ->put(route('proprietaire.rations.update', $ration), ['grammes_par_jour' => 100, 'actif' => 0])
        ->assertSessionHasNoErrors();

    expect(Tache::where('type', 'distribution')->firstOrFail()->statut)->toBe(StatutTache::Annulee);
});

// --- Autonomie et alertes ---

it("calcule l'autonomie et signale un stock faible", function () {
    lotAlimentation(40);
    $faible = alimentTest(['stock_kg' => 10]);
    $suffisant = alimentTest(['stock_kg' => 20]);
    rationTest($faible, CategorieAlimentation::Engraissement, 100);
    rationTest($suffisant, CategorieAlimentation::Engraissement, 100);

    $etats = app(GestionAliments::class)->etatStocks()->keyBy(fn ($e) => $e['aliment']->id);

    expect($etats[$faible->id]['consommation'])->toBe(4.0);
    expect($etats[$faible->id]['autonomie'])->toBe(2.5);
    expect($etats[$faible->id]['bas'])->toBeTrue();
    expect($etats[$suffisant->id]['autonomie'])->toBe(5.0);
    expect($etats[$suffisant->id]['bas'])->toBeFalse();
});

it("prévient le propriétaire d'un stock faible, une seule fois par jour", function () {
    lotAlimentation(40);
    rationTest(alimentTest(['stock_kg' => 5]), CategorieAlimentation::Engraissement, 100);

    $aliments = app(GestionAliments::class);

    expect($aliments->notifierAlertes())->toBe(1);
    expect($aliments->notifierAlertes())->toBe(0);
    expect($this->proprietaire->notifications()->where('type', AlerteStockAliment::class)->count())->toBe(1);
});

it('exécute la commande de vérification des stocks', function () {
    $this->artisan('aliments:verifier-stocks')->assertSuccessful();
});

it('affiche le bandeau de stock faible au propriétaire', function () {
    lotAlimentation(40);
    rationTest(alimentTest(['nom' => 'Aliment Rouge', 'stock_kg' => 5]), CategorieAlimentation::Engraissement, 100);

    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.tableau_de_bord'))
        ->assertOk()
        ->assertSee('Stock faible')
        ->assertSee('Aliment Rouge');
});

// --- Configuration par le propriétaire ---

it('permet au propriétaire de créer un aliment', function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.aliments.store'), ['nom' => 'Granulés X', 'poids_sac_kg' => 25])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('aliments', ['nom' => 'Granulés X', 'actif' => true]);
});

it("corrige le stock par ajustement mais refuse un stock négatif", function () {
    $aliment = alimentTest(['stock_kg' => 10]);
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.aliments.ajustements.store', $aliment), ['quantite' => 5, 'motif' => 'Inventaire'])
        ->assertSessionHasNoErrors();
    expect((float) $aliment->fresh()->stock_kg)->toBe(15.0);

    $this->post(route('proprietaire.aliments.ajustements.store', $aliment), ['quantite' => -50, 'motif' => 'Casse'])
        ->assertSessionHasErrors('action');
    expect((float) $aliment->fresh()->stock_kg)->toBe(15.0);
});

it('crée une ration et refuse un doublon aliment/catégorie', function () {
    $aliment = alimentTest();
    $this->actingAs($this->proprietaire);

    $donnees = ['categorie' => 'male', 'aliment_id' => $aliment->id, 'grammes_par_jour' => 150];

    $this->post(route('proprietaire.rations.store'), $donnees)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('rations', ['categorie' => 'male', 'aliment_id' => $aliment->id, 'grammes_par_jour' => 150]);

    $this->post(route('proprietaire.rations.store'), $donnees)->assertSessionHasErrors('aliment_id');
    expect(Ration::count())->toBe(1);
});

it("refuse de désactiver un aliment utilisé par une ration active", function () {
    $aliment = alimentTest();
    rationTest($aliment, CategorieAlimentation::Male);

    $this->actingAs($this->proprietaire)
        ->put(route('proprietaire.aliments.update', $aliment), ['nom' => $aliment->nom, 'poids_sac_kg' => 25, 'actif' => 0])
        ->assertSessionHasErrors('actif');

    expect($aliment->fresh()->actif)->toBeTrue();
});

// --- Pages ---

it('affiche la distribution du jour au gestionnaire', function () {
    rationTest(alimentTest(['nom' => 'Granulés Test']), CategorieAlimentation::Male, 150);
    Reproducteur::factory()->male()->count(2)->create();

    $this->get(route('gestionnaire.alimentation.index'))
        ->assertOk()
        ->assertSee('Granulés Test')
        ->assertSee('0,30');

    $this->get(route('gestionnaire.plus'))->assertOk()->assertSee('Alimentation');
});

it('affiche la tâche de distribution dans la checklist', function () {
    rationTest(alimentTest(['nom' => 'Granulés Test']), CategorieAlimentation::Male);
    Reproducteur::factory()->male()->create();

    $this->get(route('gestionnaire.taches.index'))
        ->assertOk()
        ->assertSee('Distribuer Granulés Test');
});

it('affiche la consommation réelle et théorique et signale une anomalie', function () {
    $aliment = alimentTest();

    Distribution::create([
        'aliment_id' => $aliment->id, 'categorie' => CategorieAlimentation::Male,
        'date_distribution' => today()->subDay(), 'nombre_animaux' => 2, 'grammes_par_animal' => 100,
        'quantite_theorique_kg' => 10, 'quantite_servie_kg' => 5,
    ]);

    $this->actingAs($this->proprietaire);

    $this->get(route('proprietaire.consommation'))->assertOk()->assertSee('Anomalie');
    $this->get(route('proprietaire.aliments'))->assertOk();
    $this->get(route('proprietaire.rations.index'))->assertOk();
});

it('sépare les accès du gestionnaire et du propriétaire', function () {
    $this->get(route('proprietaire.aliments'))->assertForbidden();
    $this->get(route('proprietaire.rations.index'))->assertForbidden();
    $this->get(route('proprietaire.consommation'))->assertForbidden();

    $this->actingAs($this->proprietaire)
        ->get(route('gestionnaire.alimentation.index'))
        ->assertForbidden();
});