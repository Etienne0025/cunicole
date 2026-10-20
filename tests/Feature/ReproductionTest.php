<?php

use App\Enums\StatutCage;
use App\Enums\StatutSaillie;
use App\Enums\TypeCage;
use App\Models\Cage;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Utilisateur;
use App\Services\PlanningElevage;

beforeEach(function () {
    $this->actingAs(Utilisateur::factory()->create());
    $this->femelle = Reproducteur::factory()->create();
    $this->male = Reproducteur::factory()->male()->create();
});

function nouvelleSaillie(Reproducteur $femelle, Reproducteur $male, int $joursPasses, StatutSaillie $statut = StatutSaillie::EnAttente, array $extra = []): Saillie
{
    return Saillie::factory()->create(array_merge([
        'femelle_id' => $femelle->id,
        'male_id' => $male->id,
        'date_saillie' => today()->subDays($joursPasses),
        'statut' => $statut,
    ], $extra));
}

it('enregistre une saillie', function () {
    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $this->femelle->id,
        'male_id' => $this->male->id,
        'date_saillie' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('saillies', ['femelle_id' => $this->femelle->id, 'statut' => 'en_attente']);
});

it('bloque une saillie consanguine et la trace dans le registre', function () {
    $fille = Reproducteur::factory()->create(['pere_id' => $this->male->id]);

    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $fille->id,
        'male_id' => $this->male->id,
        'date_saillie' => today()->toDateString(),
    ])->assertSessionHasErrors('male_id');

    $this->assertDatabaseCount('saillies', 0);
    $this->assertDatabaseHas('blocages_consanguinite', ['femelle_id' => $fille->id, 'lien' => 'père et fille']);
});

it('refuse un animal trop jeune', function () {
    $jeune = Reproducteur::factory()->create(['date_naissance' => today()->subMonths(3)->toDateString()]);

    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $jeune->id,
        'male_id' => $this->male->id,
        'date_saillie' => today()->toDateString(),
    ])->assertSessionHasErrors('femelle_id');
});

it('refuse une deuxième saillie pendant une saillie en cours', function () {
    nouvelleSaillie($this->femelle, $this->male, 3);

    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $this->femelle->id,
        'male_id' => $this->male->id,
        'date_saillie' => today()->toDateString(),
    ])->assertSessionHasErrors('femelle_id');
});

it('autorise une nouvelle saillie après une palpation négative', function () {
    nouvelleSaillie($this->femelle, $this->male, 20, StatutSaillie::NonGestante);

    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $this->femelle->id,
        'male_id' => $this->male->id,
        'date_saillie' => today()->toDateString(),
    ])->assertSessionHasNoErrors();
});

it("calcule les dates prévues à partir de la saillie", function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 0);

    expect($saillie->datePrevue('palpation')->toDateString())->toBe(today()->addDays(12)->toDateString());
    expect($saillie->datePrevue('boite_nids')->toDateString())->toBe(today()->addDays(28)->toDateString());
    expect($saillie->datePrevue('sevrage')->toDateString())->toBe(today()->addDays(60)->toDateString());
});

it('enregistre une palpation négative comme un échec', function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 13);

    $this->post(route('gestionnaire.saillies.palpation', $saillie), [
        'resultat' => 'non_gestante',
        'date_palpation' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($saillie->fresh()->statut)->toBe(StatutSaillie::NonGestante);
});

it("refuse une mise bas pour une femelle non gestante", function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 40, StatutSaillie::NonGestante);

    $this->post(route('gestionnaire.saillies.mise_bas', $saillie), [
        'date_mise_bas' => today()->toDateString(),
        'nes_vivants' => 6,
        'morts_nes' => 0,
    ])->assertSessionHasErrors('action');
});

it('suit le cycle complet jusqu\'au lot d\'engraissement', function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 62);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Engraissement, 'capacite' => 8]);

    $this->post(route('gestionnaire.saillies.palpation', $saillie), [
        'resultat' => 'gestante', 'date_palpation' => today()->subDays(50)->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->post(route('gestionnaire.saillies.boite_nids', $saillie), [
        'boite_nids_le' => today()->subDays(34)->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->post(route('gestionnaire.saillies.mise_bas', $saillie), [
        'date_mise_bas' => today()->subDays(31)->toDateString(), 'nes_vivants' => 8, 'morts_nes' => 1,
    ])->assertSessionHasNoErrors();

    expect($saillie->fresh()->statut)->toBe(StatutSaillie::MiseBas);

    $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
        'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 8, 'cage_id' => $cage->id,
    ])->assertSessionHasNoErrors();

    expect($saillie->fresh()->statut)->toBe(StatutSaillie::Sevree);
    expect($cage->fresh()->statut)->toBe(StatutCage::Occupee);
    $this->assertDatabaseHas('lots_engraissement', [
        'code' => 'LOT-'.today()->year.'-001', 'effectif_initial' => 8, 'cage_id' => $cage->id,
    ]);
});

it('refuse un sevrage avec plus de sevrés que de nés vivants', function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 62, StatutSaillie::MiseBas);
    $saillie->portee()->create(['date_mise_bas' => today()->subDays(31), 'nes_vivants' => 5, 'morts_nes' => 0]);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Engraissement, 'capacite' => 8]);

    $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
        'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 7, 'cage_id' => $cage->id,
    ])->assertSessionHasErrors('nombre_sevres');
});

it('refuse un sevrage dans une cage trop petite ou déjà occupée', function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 62, StatutSaillie::MiseBas);
    $saillie->portee()->create(['date_mise_bas' => today()->subDays(31), 'nes_vivants' => 8, 'morts_nes' => 0]);

    $petite = Cage::factory()->activee()->create(['type' => TypeCage::Engraissement, 'capacite' => 4]);
    $occupee = Cage::factory()->occupee()->create(['type' => TypeCage::Engraissement, 'capacite' => 8]);

    foreach ([$petite, $occupee] as $cage) {
        $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
            'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 8, 'cage_id' => $cage->id,
        ])->assertSessionHasErrors('cage_id');
    }

    expect($saillie->fresh()->statut)->toBe(StatutSaillie::MiseBas);
});

it('clôture une portée sans lot quand aucun lapereau ne survit', function () {
    $saillie = nouvelleSaillie($this->femelle, $this->male, 62, StatutSaillie::MiseBas);
    $saillie->portee()->create(['date_mise_bas' => today()->subDays(31), 'nes_vivants' => 3, 'morts_nes' => 0]);

    $this->post(route('gestionnaire.saillies.sevrage', $saillie), [
        'date_sevrage' => today()->toDateString(), 'nombre_sevres' => 0,
    ])->assertSessionHasNoErrors();

    expect($saillie->fresh()->statut)->toBe(StatutSaillie::Sevree);
    $this->assertDatabaseCount('lots_engraissement', 0);
});

it('place dans le planning uniquement les échéances atteintes', function () {
    $autre = Reproducteur::factory()->create();
    $due = nouvelleSaillie($this->femelle, $this->male, 13);
    nouvelleSaillie($autre, $this->male, 5);

    $planning = app(PlanningElevage::class)->pour();

    expect($planning['palpations'])->toHaveCount(1);
    expect($planning['palpations']->first()->id)->toBe($due->id);
});

it('affiche le planning au gestionnaire', function () {
    nouvelleSaillie($this->femelle, $this->male, 13);

    $this->get(route('gestionnaire.elevage'))->assertOk()->assertSee($this->femelle->matricule);
});

it('affiche la reproduction au propriétaire et enregistre son objectif', function () {
    $this->actingAs(Utilisateur::factory()->proprietaire()->create());

    $this->get(route('proprietaire.reproduction'))->assertOk();

    $this->post(route('proprietaire.reproduction.objectif'), ['objectif' => 40])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('objectifs_naissances', ['annee' => today()->year, 'mois' => today()->month, 'objectif' => 40]);
});

it("interdit les écrans de reproduction du gestionnaire au propriétaire", function () {
    $this->actingAs(Utilisateur::factory()->proprietaire()->create());

    $this->get(route('gestionnaire.elevage'))->assertForbidden();
});