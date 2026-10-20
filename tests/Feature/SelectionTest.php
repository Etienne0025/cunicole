<?php

use App\Enums\StatutCage;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\TypeCage;
use App\Models\Cage;
use App\Models\CritereSelection;
use App\Models\LotEngraissement;
use App\Models\Portee;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Utilisateur;

beforeEach(function () {
    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function lotPret(array $portee = [], array $lot = []): LotEngraissement
{
    $mere = Reproducteur::factory()->create();
    $pere = Reproducteur::factory()->male()->create();

    $saillie = Saillie::factory()->create([
        'femelle_id' => $mere->id, 'male_id' => $pere->id,
        'date_saillie' => today()->subDays(150), 'statut' => StatutSaillie::Sevree,
    ]);

    $portee = Portee::create(array_merge([
        'saillie_id' => $saillie->id, 'date_mise_bas' => today()->subDays(100),
        'nes_vivants' => 8, 'morts_nes' => 0, 'date_sevrage' => today()->subDays(70), 'sevres' => 8,
    ], $portee));

    $cage = Cage::factory()->occupee()->create(['type' => TypeCage::Engraissement, 'capacite' => 8]);

    return LotEngraissement::create(array_merge([
        'code' => 'LOT-TEST-001', 'portee_id' => $portee->id, 'cage_id' => $cage->id,
        'effectif_initial' => 8, 'effectif_actuel' => 8,
        'date_debut' => today()->subDays(70), 'statut' => StatutLot::EnCours,
    ], $lot));
}

function donneesConversion(Cage $cage, array $surcharge = []): array
{
    return array_merge([
        'matricule' => 'ln-001', 'sexe' => 'femelle', 'poids_kg' => 2.9, 'cage_id' => $cage->id,
    ], $surcharge);
}

it('enregistre la pesée d\'un lot', function () {
    $lot = lotPret();

    $this->post(route('gestionnaire.lots.pesees.store', $lot), [
        'date_pesee' => today()->toDateString(), 'poids_moyen_kg' => 2.4, 'nombre_pese' => 5,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pesees_lots', ['lot_id' => $lot->id, 'nombre_pese' => 5]);
});

it('refuse de peser plus de lapins que l\'effectif', function () {
    $lot = lotPret();

    $this->post(route('gestionnaire.lots.pesees.store', $lot), [
        'date_pesee' => today()->toDateString(), 'poids_moyen_kg' => 2.4, 'nombre_pese' => 20,
    ])->assertSessionHasErrors('nombre_pese');
});

it('convertit un lapin en reproducteur avec sa généalogie', function () {
    $lot = lotPret();
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage))
        ->assertSessionHasNoErrors();

    $animal = Reproducteur::where('matricule', 'LN-001')->firstOrFail();
    $saillie = $lot->portee->saillie;

    expect($animal->mere_id)->toBe($saillie->femelle_id);
    expect($animal->pere_id)->toBe($saillie->male_id);
    expect($animal->date_naissance->toDateString())->toBe($lot->portee->date_mise_bas->toDateString());
    expect($animal->lot_origine_id)->toBe($lot->id);
    expect($animal->valide_le)->toBeNull();
    expect($animal->cage_id)->toBe($cage->id);
    expect($cage->fresh()->statut)->toBe(StatutCage::Occupee);
    expect($lot->fresh()->effectif_actuel)->toBe(7);
    $this->assertDatabaseHas('pesees', ['reproducteur_id' => $animal->id, 'poids_kg' => 2.9]);
});

it('refuse un poids insuffisant', function () {
    $lot = lotPret();
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage, ['poids_kg' => 1.8]))
        ->assertSessionHasErrors('poids_kg');

    $this->assertDatabaseCount('lots_engraissement', 1);
    expect(Reproducteur::where('matricule', 'LN-001')->exists())->toBeFalse();
});

it('refuse un lot trop jeune', function () {
    $lot = lotPret(['date_mise_bas' => today()->subDays(60)]);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage))
        ->assertSessionHasErrors('action');
});

it('refuse une mère trop peu prolifique', function () {
    $lot = lotPret(['nes_vivants' => 4]);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage))
        ->assertSessionHasErrors('action');
});

it('annule la conversion si la cage est inadaptée', function () {
    $lot = lotPret();
    $cageMale = Cage::factory()->activee()->create(['type' => TypeCage::Male]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cageMale))
        ->assertSessionHasErrors('cage_id');

    expect(Reproducteur::where('matricule', 'LN-001')->exists())->toBeFalse();
    expect($lot->fresh()->effectif_actuel)->toBe(8);
});

it('termine le lot et libère sa cage quand le dernier lapin est converti', function () {
    $lot = lotPret([], ['effectif_actuel' => 1]);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage))
        ->assertSessionHasNoErrors();

    expect($lot->fresh()->statut)->toBe(StatutLot::Termine);
    expect($lot->cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
});

it('refuse un matricule déjà utilisé', function () {
    $lot = lotPret();
    Reproducteur::factory()->create(['matricule' => 'LN-001']);
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);

    $this->post(route('gestionnaire.lots.convertir', $lot), donneesConversion($cage))
        ->assertSessionHasErrors('matricule');
});

it('interdit la saillie d\'un reproducteur non validé', function () {
    $nonValide = Reproducteur::factory()->create(['valide_le' => null]);
    $male = Reproducteur::factory()->male()->create();

    $this->post(route('gestionnaire.saillies.store'), [
        'femelle_id' => $nonValide->id, 'male_id' => $male->id, 'date_saillie' => today()->toDateString(),
    ])->assertSessionHasErrors('femelle_id');
});

it('permet au propriétaire de valider un nouveau reproducteur', function () {
    $animal = Reproducteur::factory()->create(['valide_le' => null]);

    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.selection.valider', $animal))
        ->assertSessionHasNoErrors();

    expect($animal->fresh()->estValide())->toBeTrue();
    expect($animal->fresh()->valide_par_id)->toBe($this->proprietaire->id);
});

it('permet au propriétaire de refuser un reproducteur et libère la cage', function () {
    $cage = Cage::factory()->activee()->create(['type' => TypeCage::Femelle]);
    $animal = Reproducteur::factory()->create(['valide_le' => null]);
    app(\App\Services\PlacementCages::class)->placer($animal, $cage);

    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.selection.refuser', $animal))
        ->assertSessionHasNoErrors();

    expect($animal->fresh()->statut)->toBe(StatutReproducteur::Reforme);
    expect($animal->fresh()->cage_id)->toBeNull();
    expect($cage->fresh()->statut)->toBe(StatutCage::ANettoyer);
});

it('permet au propriétaire de modifier les critères', function () {
    $this->actingAs($this->proprietaire)
        ->put(route('proprietaire.selection.criteres'), [
            'poids_min_kg' => 3.0, 'age_min_jours' => 100, 'prolificite_mere_min' => 7,
        ])->assertSessionHasNoErrors();

    $criteres = CritereSelection::actuel();
    expect((float) $criteres->poids_min_kg)->toBe(3.0);
    expect($criteres->age_min_jours)->toBe(100);
});

it('affiche la page de sélection au propriétaire', function () {
    $this->actingAs($this->proprietaire)
        ->get(route('proprietaire.selection'))
        ->assertOk()
        ->assertSee('Critères de sélection');
});

it('interdit au propriétaire de convertir un lot', function () {
    $lot = lotPret();

    $this->actingAs($this->proprietaire)
        ->get(route('gestionnaire.lots.show', $lot))
        ->assertForbidden();
});