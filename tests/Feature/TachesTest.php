<?php

use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutSaillie;
use App\Enums\StatutTache;
use App\Enums\TypeTache;
use App\Models\Cage;
use App\Models\ClotureJournee;
use App\Models\LotEngraissement;
use App\Models\Saillie;
use App\Models\Tache;
use App\Models\Utilisateur;
use App\Services\GestionTaches;

beforeEach(function () {
    config(['cunicole.taches.quotidiennes' => []]);

    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function saillieDeJours(int $jours, StatutSaillie $statut = StatutSaillie::EnAttente): Saillie
{
    return Saillie::factory()->create([
        'date_saillie' => today()->subDays($jours),
        'statut' => $statut,
    ]);
}

// --- Génération ---

it('génère une tâche de palpation pour une saillie échue', function () {
    $saillie = saillieDeJours(13);

    app(GestionTaches::class)->synchroniser();

    $this->assertDatabaseHas('taches', [
        'type' => 'palpation', 'source_type' => 'saillie', 'source_id' => $saillie->id, 'statut' => 'a_faire',
    ]);
});

it('ne génère rien pour une saillie dont l\'échéance n\'est pas atteinte', function () {
    saillieDeJours(5);

    app(GestionTaches::class)->synchroniser();

    $this->assertDatabaseCount('taches', 0);
});

it('ne crée pas de doublons à la deuxième synchronisation', function () {
    saillieDeJours(13);
    $gestion = app(GestionTaches::class);

    expect($gestion->synchroniser())->toBe(1);
    expect($gestion->synchroniser())->toBe(0);
    $this->assertDatabaseCount('taches', 1);
});

it('génère une tâche de nettoyage pour une cage à nettoyer', function () {
    Cage::factory()->activee()->create(['statut' => StatutCage::ANettoyer]);

    app(GestionTaches::class)->synchroniser();

    expect(Tache::where('type', 'nettoyage_cage')->count())->toBe(1);
});

it('génère une tâche de pesée pour un lot non pesé depuis une semaine', function () {
    LotEngraissement::create([
        'code' => 'LOT-T-001', 'effectif_initial' => 5, 'effectif_actuel' => 5,
        'date_debut' => today()->subDays(10), 'statut' => StatutLot::EnCours,
    ]);

    app(GestionTaches::class)->synchroniser();

    expect(Tache::where('type', 'pesee_lot')->count())->toBe(1);
});

it('génère les tâches quotidiennes de la configuration', function () {
    config(['cunicole.taches.quotidiennes' => ["Contrôler l'eau", 'Ronde sanitaire']]);

    app(GestionTaches::class)->synchroniser();

    expect(Tache::where('type', 'quotidienne')->count())->toBe(2);
});

it('ne génère plus de tâche pour une journée clôturée', function () {
    ClotureJournee::create([
        'date' => today(), 'utilisateur_id' => $this->gestionnaire->id, 'cloturee_le' => now(),
        'total' => 0, 'faites' => 0, 'non_faites' => 0,
    ]);
    saillieDeJours(13);

    expect(app(GestionTaches::class)->synchroniser())->toBe(0);
    $this->assertDatabaseCount('taches', 0);
});

// --- Résolution automatique ---

it('termine la tâche de palpation dès que le résultat est enregistré', function () {
    $saillie = saillieDeJours(13);
    app(GestionTaches::class)->synchroniser();

    $this->post(route('gestionnaire.saillies.palpation', $saillie), [
        'resultat' => 'gestante', 'date_palpation' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $tache = Tache::where('type', 'palpation')->firstOrFail();
    expect($tache->statut)->toBe(StatutTache::Faite);
    expect($tache->automatique)->toBeTrue();
    expect($tache->faite_le)->not->toBeNull();
});

it('termine la tâche de nettoyage quand la cage est remise à vide', function () {
    $cage = Cage::factory()->activee()->create(['statut' => StatutCage::ANettoyer]);
    app(GestionTaches::class)->synchroniser();

    $this->post(route('gestionnaire.cages.statut', $cage), ['statut' => 'vide'])
        ->assertSessionHasNoErrors();

    expect(Tache::where('type', 'nettoyage_cage')->firstOrFail()->statut)->toBe(StatutTache::Faite);
});

it('termine la tâche de pesée quand le lot est pesé', function () {
    $lot = LotEngraissement::create([
        'code' => 'LOT-T-002', 'effectif_initial' => 5, 'effectif_actuel' => 5,
        'date_debut' => today()->subDays(10), 'statut' => StatutLot::EnCours,
    ]);
    app(GestionTaches::class)->synchroniser();

    $this->post(route('gestionnaire.lots.pesees.store', $lot), [
        'date_pesee' => today()->toDateString(), 'poids_moyen_kg' => 1.8, 'nombre_pese' => 3,
    ])->assertSessionHasNoErrors();

    expect(Tache::where('type', 'pesee_lot')->firstOrFail()->statut)->toBe(StatutTache::Faite);
});

// --- Cochage ---

it('coche une tâche quotidienne avec horodatage', function () {
    $tache = Tache::factory()->create();

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasNoErrors();

    $tache->refresh();
    expect($tache->statut)->toBe(StatutTache::Faite);
    expect($tache->faite_par_id)->toBe($this->gestionnaire->id);
    expect($tache->faite_le)->not->toBeNull();
    expect($tache->automatique)->toBeFalse();
});

it('refuse de cocher une tâche qui se termine automatiquement', function () {
    $tache = Tache::factory()->create(['type' => TypeTache::Palpation]);

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasErrors('action');

    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
});

it("refuse de cocher une tâche d'un autre jour", function () {
    $tache = Tache::factory()->create(['date_prevue' => today()->subDay()]);

    $this->post(route('gestionnaire.taches.cocher', $tache))->assertSessionHasErrors('action');
});

it('annule un cochage fait à la main', function () {
    $tache = Tache::factory()->faite()->create(['faite_par_id' => $this->gestionnaire->id]);

    $this->post(route('gestionnaire.taches.decocher', $tache))->assertSessionHasNoErrors();

    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
    expect($tache->fresh()->faite_le)->toBeNull();
});

it("refuse d'annuler une tâche terminée automatiquement", function () {
    $tache = Tache::factory()->faite()->create(['automatique' => true]);

    $this->post(route('gestionnaire.taches.decocher', $tache))->assertSessionHasErrors('action');
});

// --- Clôture ---

it('exige une justification pour chaque tâche non faite', function () {
    $tache = Tache::factory()->create();

    $this->post(route('gestionnaire.taches.cloturer'), ['date' => today()->toDateString()])
        ->assertSessionHasErrors(['justifications.'.$tache->id]);

    $this->assertDatabaseCount('clotures_journees', 0);
    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
});

it('clôture la journée avec le bilan et les justifications', function () {
    $nonFaite = Tache::factory()->create();
    Tache::factory()->faite()->create();

    $this->post(route('gestionnaire.taches.cloturer'), [
        'date' => today()->toDateString(),
        'justifications' => [$nonFaite->id => 'Pluie forte, reporté'],
        'commentaire' => 'RAS',
    ])->assertSessionHasNoErrors();

    expect($nonFaite->fresh()->statut)->toBe(StatutTache::NonFaite);
    expect($nonFaite->fresh()->justification)->toBe('Pluie forte, reporté');
    $this->assertDatabaseHas('clotures_journees', [
        'total' => 2, 'faites' => 1, 'non_faites' => 1, 'utilisateur_id' => $this->gestionnaire->id,
    ]);
});

it('refuse de clôturer deux fois la même journée', function () {
    ClotureJournee::create([
        'date' => today(), 'utilisateur_id' => $this->gestionnaire->id, 'cloturee_le' => now(),
        'total' => 0, 'faites' => 0, 'non_faites' => 0,
    ]);

    $this->post(route('gestionnaire.taches.cloturer'), ['date' => today()->toDateString()])
        ->assertSessionHasErrors('date');
});

it('refuse de clôturer une journée à venir', function () {
    $this->post(route('gestionnaire.taches.cloturer'), ['date' => today()->addDay()->toDateString()])
        ->assertSessionHasErrors('date');
});

it("permet de clôturer une journée passée oubliée", function () {
    $hier = today()->subDay();
    $tache = Tache::factory()->create(['date_prevue' => $hier]);

    $this->post(route('gestionnaire.taches.cloturer'), [
        'date' => $hier->toDateString(),
        'justifications' => [$tache->id => 'Oubli de clôture, tâche non réalisée'],
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('clotures_journees', ['total' => 1, 'non_faites' => 1]);
});

it('signale les journées passées non clôturées', function () {
    Tache::factory()->create(['date_prevue' => today()->subDays(2)]);

    expect(app(GestionTaches::class)->joursNonClotures()->all())->toBe([today()->subDays(2)->toDateString()]);
});

// --- Supervision et rapports (propriétaire) ---

it('fournit la progression du jour au propriétaire', function () {
    Tache::factory()->faite()->create();
    Tache::factory()->create();

    $this->actingAs($this->proprietaire)
        ->getJson(route('proprietaire.supervision.progression'))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('faites', 1)
        ->assertJsonPath('pourcentage', 50);
});

it("affiche l'écran de supervision au propriétaire", function () {
    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.supervision'))
        ->assertOk();
});

it('affiche le rapport quotidien avec les justifications', function () {
    $hier = today()->subDay();
    Tache::factory()->create([
        'date_prevue' => $hier, 'statut' => StatutTache::NonFaite,
        'titre' => 'Ronde sanitaire', 'justification' => 'Panne de courant',
    ]);
    ClotureJournee::create([
        'date' => $hier, 'utilisateur_id' => $this->gestionnaire->id, 'cloturee_le' => now(),
        'total' => 1, 'faites' => 0, 'non_faites' => 1,
    ]);

    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.rapports.show', $hier->toDateString()))
        ->assertOk()
        ->assertSee('Panne de courant');

    $this->get(route('proprietaire.rapports.index'))->assertOk();
});

it("renvoie une erreur 404 pour un rapport inexistant", function () {
    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.rapports.show', '2020-01-01'))
        ->assertNotFound();
});

// --- Accès ---

it('interdit la checklist du gestionnaire au propriétaire', function () {
    $this->actingAs($this->proprietaire)
        ->get(route('gestionnaire.taches.index'))
        ->assertForbidden();
});

it('interdit la supervision au gestionnaire', function () {
    $this->get(route('proprietaire.supervision'))->assertForbidden();
    $this->get(route('proprietaire.rapports.index'))->assertForbidden();
});

it('affiche la checklist au gestionnaire', function () {
    saillieDeJours(13);

    $this->get(route('gestionnaire.taches.index'))
        ->assertOk()
        ->assertSee('Palpation');
});

it('exécute la commande de génération', function () {
    saillieDeJours(13);

    $this->artisan('taches:generer')->assertSuccessful();

    expect(Tache::where('type', 'palpation')->count())->toBe(1);
});