<?php

use App\Models\Batiment;
use App\Models\Cage;
use App\Models\Utilisateur;

beforeEach(function () {
    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
});

it('crée un lot de cages avec codes et uuid', function () {
    $batiment = Batiment::factory()->create();

    $this->actingAs($this->gestionnaire)
        ->post(route('gestionnaire.cages.store'), [
            'batiment_id' => $batiment->id,
            'prefixe' => 'a',
            'nombre' => 3,
            'type' => 'femelle',
            'capacite' => 1,
        ])
        ->assertRedirect(route('gestionnaire.cages.index'));

    expect(Cage::orderBy('code')->pluck('code')->all())->toBe(['A-001', 'A-002', 'A-003']);
    expect(Cage::first()->uuid)->not->toBeEmpty();
});

it('poursuit la numérotation existante', function () {
    $batiment = Batiment::factory()->create();
    Cage::factory()->create(['batiment_id' => $batiment->id, 'code' => 'A-005']);

    $this->actingAs($this->gestionnaire)
        ->post(route('gestionnaire.cages.store'), [
            'batiment_id' => $batiment->id,
            'prefixe' => 'A',
            'nombre' => 2,
            'type' => 'femelle',
            'capacite' => 1,
        ]);

    expect(Cage::where('code', 'A-006')->exists())->toBeTrue();
    expect(Cage::where('code', 'A-007')->exists())->toBeTrue();
});

it('génère la planche PDF de QR codes', function () {
    $cage = Cage::factory()->create();

    $this->actingAs($this->gestionnaire)
        ->post(route('gestionnaire.cages.planche'), ['cages' => [$cage->uuid]])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it("active une cage", function () {
    $cage = Cage::factory()->create();

    $this->actingAs($this->gestionnaire)
        ->post(route('gestionnaire.cages.activer', $cage))
        ->assertRedirect();

    expect($cage->fresh()->estActivee())->toBeTrue();
});

it('refuse de supprimer une cage occupée', function () {
    $cage = Cage::factory()->occupee()->create();

    $this->actingAs($this->gestionnaire)
        ->delete(route('gestionnaire.cages.destroy', $cage))
        ->assertSessionHasErrors('action');

    $this->assertDatabaseHas('cages', ['id' => $cage->id]);
});

it('interdit la création de cages au propriétaire', function () {
    $this->actingAs($this->proprietaire)
        ->get(route('gestionnaire.cages.create'))
        ->assertForbidden();
});

it('affiche la cartographie au propriétaire', function () {
    $batiment = Batiment::factory()->create(['nom' => 'Bâtiment Test']);
    Cage::factory()->activee()->create(['batiment_id' => $batiment->id]);

    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.cartographie'))
        ->assertOk()
        ->assertSee('Bâtiment Test');
});