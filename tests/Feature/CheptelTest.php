<?php

use App\Enums\StatutCage;
use App\Enums\StatutReproducteur;
use App\Enums\TypeCage;
use App\Models\Cage;
use App\Models\Reproducteur;
use App\Models\Utilisateur;

beforeEach(function () {
    $this->gestionnaire = Utilisateur::factory()->create();
    $this->actingAs($this->gestionnaire);
});

function donneesReproducteur(array $surcharge = []): array
{
    return array_merge([
        'matricule' => 'ab-123',
        'sexe' => 'femelle',
        'date_naissance' => now()->subMonths(7)->toDateString(),
        'origine' => 'ferme',
        'date_entree' => now()->subMonths(2)->toDateString(),
    ], $surcharge);
}

it('enregistre un reproducteur avec matricule en majuscules', function () {
    $this->post(route('gestionnaire.reproducteurs.store'), donneesReproducteur())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reproducteurs', ['matricule' => 'AB-123', 'statut' => 'actif']);
});

it('refuse un matricule déjà utilisé', function () {
    Reproducteur::factory()->create(['matricule' => 'AB-123']);

    $this->post(route('gestionnaire.reproducteurs.store'), donneesReproducteur())
        ->assertSessionHasErrors('matricule');
});

it('refuse une date de naissance dans le futur', function () {
    $this->post(route('gestionnaire.reproducteurs.store'), donneesReproducteur([
        'date_naissance' => now()->addDay()->toDateString(),
    ]))->assertSessionHasErrors('date_naissance');
});

it('place un animal et marque la cage occupée', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $cage->id])
        ->assertSessionHasNoErrors();

    expect($cage->fresh()->statut)->toBe(StatutCage::Occupee);
    expect($lapine->fresh()->cage_id)->toBe($cage->id);
    $this->assertDatabaseHas('mouvements_cages', ['reproducteur_id' => $lapine->id, 'code_destination' => $cage->code]);
});

it("marque l'ancienne cage à nettoyer après un déplacement", function () {
    $ancienne = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $nouvelle = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $ancienne->id]);
    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $nouvelle->id]);

    expect($ancienne->fresh()->statut)->toBe(StatutCage::ANettoyer);
    expect($nouvelle->fresh()->statut)->toBe(StatutCage::Occupee);
});

it('refuse une cage pleine', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle, 'capacite' => 1]);
    $premiere = Reproducteur::factory()->create();
    $seconde = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $premiere), ['cage_id' => $cage->id]);

    $this->post(route('gestionnaire.reproducteurs.deplacer', $seconde), ['cage_id' => $cage->id])
        ->assertSessionHasErrors('cage_id');
});

it('refuse une femelle dans une cage de mâle', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Male]);
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $cage->id])
        ->assertSessionHasErrors('cage_id');
});

it('refuse une cage non activée', function () {
    $cage = Cage::factory()->create(['type' => TypeCage::Femelle]);
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $cage->id])
        ->assertSessionHasErrors('cage_id');
});

it('refuse une cage à nettoyer', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle, 'statut' => StatutCage::ANettoyer]);
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $cage->id])
        ->assertSessionHasErrors('cage_id');
});

it('libère la cage lors de la sortie du cheptel', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $lapine = Reproducteur::factory()->create();
    $this->post(route('gestionnaire.reproducteurs.deplacer', $lapine), ['cage_id' => $cage->id]);

    $this->post(route('gestionnaire.reproducteurs.sortie', $lapine), [
        'statut' => 'reforme',
        'date_sortie' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($lapine->fresh()->statut)->toBe(StatutReproducteur::Reforme);
    expect($lapine->fresh()->cage_id)->toBeNull();
    expect($cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
});

it('enregistre une pesée', function () {
    $lapine = Reproducteur::factory()->create();

    $this->post(route('gestionnaire.reproducteurs.pesees.store', $lapine), [
        'date_pesee' => now()->toDateString(),
        'poids_kg' => 3.85,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pesees', ['reproducteur_id' => $lapine->id, 'poids_kg' => 3.85]);
});

it('affiche le cheptel au propriétaire', function () {
    Reproducteur::factory()->create(['matricule' => 'ZZ-0001']);

    $this->actingAs(Utilisateur::factory()->proprietaire()->create())
        ->get(route('proprietaire.cheptel'))
        ->assertOk()
        ->assertSee('ZZ-0001');
});