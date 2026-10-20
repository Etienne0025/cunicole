<?php

use App\Enums\CategorieAlimentation;
use App\Enums\StatutLot;
use App\Models\Aliment;
use App\Models\Batiment;
use App\Models\Cage;
use App\Models\LotEngraissement;
use App\Models\Mortalite;
use App\Models\ProduitVeterinaire;
use App\Models\Ration;
use App\Models\Reproducteur;
use App\Models\Tache;
use App\Models\Utilisateur;
use App\Models\Vente;
use App\Services\TableauDeBord;

beforeEach(function () {
    config(['cunicole.taches.quotidiennes' => []]);

    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->proprietaire);
});

function codesAlertes(): array
{
    $service = app(TableauDeBord::class);

    return $service->alertes($service->donnees())->pluck('code')->all();
}

it('affiche le tableau de bord complet au propriétaire', function () {
    $this->get(route('proprietaire.tableau_de_bord'))
        ->assertOk()
        ->assertSee('Cages occupées')
        ->assertSee('Mises bas attendues')
        ->assertSee('Taux de mortalité')
        ->assertSee('Bénéfice net du mois')
        ->assertSee('Aucune alerte');
});

it('refuse le tableau de bord au gestionnaire', function () {
    $this->actingAs($this->gestionnaire)
        ->get(route('proprietaire.tableau_de_bord'))
        ->assertForbidden();
});

it('signale un taux de mortalité critique', function () {
    Mortalite::create([
        'cible' => 'lot', 'code_cible' => 'LOT-X', 'nombre' => 3, 'date_deces' => today(), 'cause' => 'inconnue',
    ]);

    $service = app(TableauDeBord::class);
    $alertes = $service->alertes($service->donnees());

    expect($alertes->firstWhere('code', 'mortalite')['niveau'])->toBe('critique');
    expect($alertes->first()['code'])->toBe('mortalite');   // les alertes critiques passent en premier
});

it("signale un stock d'aliment faible", function () {
    LotEngraissement::create([
        'code' => 'LOT-TB-001', 'effectif_initial' => 40, 'effectif_actuel' => 40,
        'date_debut' => today()->subDays(40), 'statut' => StatutLot::EnCours,
    ]);
    $aliment = Aliment::create(['nom' => 'Aliment Alerte', 'stock_kg' => 5, 'actif' => true]);
    Ration::create([
        'categorie' => CategorieAlimentation::Engraissement, 'aliment_id' => $aliment->id,
        'grammes_par_jour' => 100, 'actif' => true,
    ]);

    expect(codesAlertes())->toContain('stock_aliment');
});

it('signale un stock bas à la pharmacie', function () {
    ProduitVeterinaire::create([
        'nom' => 'Vaccin Bas', 'type' => 'vaccin', 'unite' => 'dose', 'stock_actuel' => 2, 'seuil_alerte' => 5,
    ]);

    expect(codesAlertes())->toContain('stock_pharmacie');
});

it('signale une journée non clôturée', function () {
    Tache::factory()->create(['date_prevue' => today()->subDays(2)]);

    expect(codesAlertes())->toContain('journees');
});

it('signale un reproducteur en attente de validation', function () {
    Reproducteur::factory()->create(['valide_le' => null]);

    expect(codesAlertes())->toContain('validation');
});

it('signale les créances à recouvrer', function () {
    Vente::create([
        'numero' => 'V-TB-0001', 'date_vente' => today(),
        'total_brut' => 10000, 'remise_pct' => 0, 'remise_montant' => 0,
        'total_net' => 10000, 'montant_paye' => 0,
    ]);

    expect(codesAlertes())->toContain('creances');
});

it("affiche les alertes sur la page du tableau de bord", function () {
    Mortalite::create([
        'cible' => 'lot', 'code_cible' => 'LOT-X', 'nombre' => 3, 'date_deces' => today(), 'cause' => 'inconnue',
    ]);

    $this->get(route('proprietaire.tableau_de_bord'))
        ->assertOk()
        ->assertSee('Urgent')
        ->assertSee('Taux de mortalité de');
});

it('marque les notifications comme lues', function () {
    $this->proprietaire->notify(new \App\Notifications\AlerteMortalite([
        'taux' => 10, 'seuil' => 5, 'deces' => 3, 'jours' => 30,
    ]));

    expect($this->proprietaire->unreadNotifications()->count())->toBe(1);

    $this->get(route('proprietaire.tableau_de_bord'))->assertOk()->assertSee('Notifications non lues');

    $this->post(route('proprietaire.notifications.lues'))->assertSessionHasNoErrors();

    expect($this->proprietaire->fresh()->unreadNotifications()->count())->toBe(0);
});

it("charge la carte d'occupation avec son actualisation automatique", function () {
    $batiment = Batiment::factory()->create(['nom' => 'Bâtiment Carte']);
    Cage::factory()->activee()->create(['batiment_id' => $batiment->id]);

    $this->get(route('proprietaire.cartographie'))
        ->assertOk()
        ->assertSee('Bâtiment Carte')
        ->assertSee('id="carte"', false);
});