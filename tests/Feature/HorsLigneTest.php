<?php

use App\Enums\CategorieAlimentation;
use App\Enums\StatutLot;
use App\Enums\StatutReproducteur;
use App\Enums\StatutSaillie;
use App\Enums\StatutTache;
use App\Enums\TypeCage;
use App\Models\ActionSynchronisee;
use App\Models\Aliment;
use App\Models\Cage;
use App\Models\Charge;
use App\Models\Distribution;
use App\Models\LotEngraissement;
use App\Models\Portee;
use App\Models\Ration;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Tache;
use App\Models\Tarif;
use App\Models\Utilisateur;
use App\Models\Vente;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['cunicole.taches.quotidiennes' => []]);

    $this->gestionnaire = Utilisateur::factory()->create();
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->actingAs($this->gestionnaire);
});

function hlAction(string $type, array $payload, array $extra = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'type' => $type,
        'libelle' => "Test {$type}",
        'effectue_le' => now()->toIso8601String(),
        'payload' => $payload,
    ], $extra);
}

function hlLot(int $effectif = 10): LotEngraissement
{
    $cage = Cage::factory()->occupee()->create(['type' => TypeCage::Engraissement, 'capacite' => 20]);

    return LotEngraissement::create([
        'code' => 'LOT-HL-'.fake()->unique()->numerify('####'),
        'cage_id' => $cage->id,
        'effectif_initial' => $effectif,
        'effectif_actuel' => $effectif,
        'date_debut' => today()->subDays(60),
        'statut' => StatutLot::EnCours,
    ]);
}

function hlRation(float $stock = 50): Ration
{
    $aliment = Aliment::create(['nom' => 'Aliment HL '.fake()->unique()->numerify('###'), 'stock_kg' => $stock, 'actif' => true]);

    return Ration::create([
        'categorie' => CategorieAlimentation::Male,
        'aliment_id' => $aliment->id,
        'grammes_par_jour' => 150,
        'actif' => true,
    ]);
}

// --- Accès ---

it('refuse la synchronisation à un visiteur et au propriétaire', function () {
    auth()->logout();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [hlAction('depense', [])]])
        ->assertUnauthorized();

    $this->actingAs($this->proprietaire)
        ->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [hlAction('depense', [])]])
        ->assertForbidden();
});

it("fournit l'état de la session avec un jeton à jour", function () {
    $this->getJson(route('gestionnaire.synchronisation.etat'))
        ->assertOk()
        ->assertJsonStructure(['csrf', 'heure']);
});

it('exige une liste de saisies', function () {
    $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => []])
        ->assertUnprocessable();
});

// --- Idempotence ---

it("n'applique jamais deux fois la même saisie, même renvoyée", function () {
    $ration = hlRation(50);
    Reproducteur::factory()->male()->count(2)->create();

    $action = hlAction('distribution', ['ration_id' => $ration->id, 'quantite_servie_kg' => '0.31']);

    foreach ([1, 2] as $_) {
        $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$action]])
            ->assertOk()
            ->assertJsonPath('resultats.0.statut', 'appliquee');
    }

    expect(Distribution::count())->toBe(1);
    expect((float) $ration->aliment->fresh()->stock_kg)->toBe(49.69);
    expect(ActionSynchronisee::count())->toBe(1);
});

it("refuse une saisie dont l'identifiant est déjà pris par un autre compte", function () {
    $action = hlAction('tache_cocher', ['tache_id' => Tache::factory()->create()->id]);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$action]])->assertOk();

    $autre = Utilisateur::factory()->create();

    $this->actingAs($autre)
        ->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$action]])
        ->assertJsonPath('resultats.0.statut', 'refusee');
});

// --- Dates ---

it('applique une saisie faite hier à la date de la veille', function () {
    $hier = now()->subDay();
    $tache = Tache::factory()->create(['date_prevue' => today()->subDay()]);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('tache_cocher', ['tache_id' => $tache->id], ['effectue_le' => $hier->toIso8601String()])],
    ])->assertJsonPath('resultats.0.statut', 'appliquee');

    $tache->refresh();

    expect($tache->statut)->toBe(StatutTache::Faite);
    expect($tache->faite_le->toDateString())->toBe($hier->toDateString());
    expect($tache->faite_par_id)->toBe($this->gestionnaire->id);
});

it("refuse de cocher aujourd'hui une tâche d'hier et garde la trace du refus", function () {
    $tache = Tache::factory()->create(['date_prevue' => today()->subDay()]);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('tache_cocher', ['tache_id' => $tache->id])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
    $this->assertDatabaseHas('actions_synchronisees', ['statut' => 'refusee', 'type' => 'tache_cocher']);
});

it('refuse une saisie trop ancienne', function () {
    $tache = Tache::factory()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('tache_cocher', ['tache_id' => $tache->id], ['effectue_le' => now()->subDays(10)->toIso8601String()])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    expect($tache->fresh()->statut)->toBe(StatutTache::AFaire);
});

it("ne se fie pas à une horloge de téléphone en avance", function () {
    $tache = Tache::factory()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('tache_cocher', ['tache_id' => $tache->id], ['effectue_le' => now()->addHours(3)->toIso8601String()])],
    ])->assertJsonPath('resultats.0.statut', 'appliquee');

    expect($tache->fresh()->faite_le->lte(now()))->toBeTrue();
});

it('refuse un type de saisie inconnu ou une date illisible', function () {
    $reponse = $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [
            hlAction('virement', []),
            hlAction('depense', [], ['effectue_le' => 'pas-une-date']),
        ],
    ])->assertOk();

    $reponse->assertJsonPath('resultats.0.statut', 'refusee');
    $reponse->assertJsonPath('resultats.1.statut', 'refusee');
});

// --- Refus ---

it("ne garde pas la trace d'un refus immédiat, mais renvoie le motif", function () {
    $ration = hlRation(0.1);
    Reproducteur::factory()->male()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('distribution', ['ration_id' => $ration->id, 'quantite_servie_kg' => '5'], ['direct' => true])],
    ])
        ->assertJsonPath('resultats.0.statut', 'refusee')
        ->assertJsonPath('resultats.0.message', fn ($message) => str_contains($message, 'Stock insuffisant'));

    expect(ActionSynchronisee::count())->toBe(0);
    expect(Distribution::count())->toBe(0);
});

it("garde la trace d'un refus constaté après coup, avec les données saisies", function () {
    $ration = hlRation(0.1);
    Reproducteur::factory()->male()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('distribution', ['ration_id' => $ration->id, 'quantite_servie_kg' => '5'])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    $journal = ActionSynchronisee::firstOrFail();

    expect($journal->statut)->toBe('refusee');
    expect($journal->payload['quantite_servie_kg'])->toBe('5');
    expect((float) $ration->aliment->fresh()->stock_kg)->toBe(0.1);
});

it('traite les saisies dans l\'ordre et continue après un refus', function () {
    $tache = Tache::factory()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [
            hlAction('tache_cocher', ['tache_id' => 999999]),
            hlAction('tache_cocher', ['tache_id' => $tache->id]),
        ],
    ])
        ->assertJsonPath('resultats.0.statut', 'refusee')
        ->assertJsonPath('resultats.1.statut', 'appliquee');
});

// --- Types de saisies ---

it('applique une distribution faite hier à la date de la veille', function () {
    $ration = hlRation(50);
    Reproducteur::factory()->male()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('distribution', ['ration_id' => $ration->id, 'quantite_servie_kg' => '0.15'], ['effectue_le' => now()->subDay()->toIso8601String()])],
    ])->assertJsonPath('resultats.0.statut', 'appliquee');

    expect(Distribution::firstOrFail()->date_distribution->toDateString())->toBe(today()->subDay()->toDateString());
});

it('enregistre les pesées', function () {
    $animal = Reproducteur::factory()->create();
    $lot = hlLot(10);

    $reponse = $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [
            hlAction('pesee_reproducteur', ['reproducteur_id' => $animal->id, 'date_pesee' => today()->toDateString(), 'poids_kg' => '3.85']),
            hlAction('pesee_lot', ['lot_id' => $lot->id, 'date_pesee' => today()->toDateString(), 'poids_moyen_kg' => '1.9', 'nombre_pese' => '4']),
        ],
    ]);

    $reponse->assertJsonPath('resultats.0.statut', 'appliquee');
    $reponse->assertJsonPath('resultats.1.statut', 'appliquee');
    $this->assertDatabaseHas('pesees', ['reproducteur_id' => $animal->id, 'poids_kg' => 3.85]);
    $this->assertDatabaseHas('pesees_lots', ['lot_id' => $lot->id, 'nombre_pese' => 4]);
});

it('refuse de peser plus de lapins que le lot ne compte', function () {
    $lot = hlLot(3);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('pesee_lot', ['lot_id' => $lot->id, 'date_pesee' => today()->toDateString(), 'poids_moyen_kg' => '1.9', 'nombre_pese' => '9'])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');
});

it('enregistre la palpation, la boîte à nids et la mise bas', function () {
    $enAttente = Saillie::factory()->create(['statut' => StatutSaillie::EnAttente, 'date_saillie' => today()->subDays(13)]);
    $gestante = Saillie::factory()->create(['statut' => StatutSaillie::Gestante, 'date_saillie' => today()->subDays(30), 'date_palpation' => today()->subDays(18)]);
    $pourMiseBas = Saillie::factory()->create(['statut' => StatutSaillie::Gestante, 'date_saillie' => today()->subDays(31), 'date_palpation' => today()->subDays(19)]);

    $reponse = $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [
            hlAction('palpation', ['saillie_id' => $enAttente->id, 'resultat' => 'gestante', 'date_palpation' => today()->toDateString()]),
            hlAction('boite_nids', ['saillie_id' => $gestante->id, 'boite_nids_le' => today()->toDateString()]),
            hlAction('mise_bas', ['saillie_id' => $pourMiseBas->id, 'date_mise_bas' => today()->toDateString(), 'nes_vivants' => '8', 'morts_nes' => '1']),
        ],
    ]);

    foreach ([0, 1, 2] as $i) {
        $reponse->assertJsonPath("resultats.{$i}.statut", 'appliquee');
    }

    expect($enAttente->fresh()->statut)->toBe(StatutSaillie::Gestante);
    expect($gestante->fresh()->boite_nids_le)->not->toBeNull();
    expect($pourMiseBas->fresh()->statut)->toBe(StatutSaillie::MiseBas);
    expect(Portee::where('saillie_id', $pourMiseBas->id)->value('nes_vivants'))->toBe(8);
});

it("refuse une mise bas pour une femelle qui n'est pas gestante", function () {
    $saillie = Saillie::factory()->create(['statut' => StatutSaillie::NonGestante, 'date_saillie' => today()->subDays(40)]);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('mise_bas', ['saillie_id' => $saillie->id, 'date_mise_bas' => today()->toDateString(), 'nes_vivants' => '5', 'morts_nes' => '0'])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');
});

it('enregistre un décès', function () {
    $animal = Reproducteur::factory()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('deces', ['cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'date_deces' => today()->toDateString(), 'cause' => 'maladie'])],
    ])->assertJsonPath('resultats.0.statut', 'appliquee');

    expect($animal->fresh()->statut)->toBe(StatutReproducteur::Mort);
});

it('exige la cause du décès', function () {
    $animal = Reproducteur::factory()->create();

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('deces', ['cible_type' => 'reproducteur', 'cible_id' => $animal->id, 'date_deces' => today()->toDateString()])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    expect($animal->fresh()->estActif())->toBeTrue();
});

it('enregistre une dépense et refuse une charge fixe', function () {
    $reponse = $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [
            hlAction('depense', ['categorie' => 'litiere', 'libelle' => 'Paille 2 bottes', 'montant' => '5000', 'date_charge' => today()->toDateString()]),
            hlAction('depense', ['categorie' => 'salaires', 'libelle' => 'Salaire', 'montant' => '40000', 'date_charge' => today()->toDateString()]),
        ],
    ]);

    $reponse->assertJsonPath('resultats.0.statut', 'appliquee');
    $reponse->assertJsonPath('resultats.1.statut', 'refusee');
    expect(Charge::count())->toBe(1);
    $this->assertDatabaseHas('charges', ['libelle' => 'Paille 2 bottes', 'utilisateur_id' => $this->gestionnaire->id]);
});

// --- Ventes ---

function hlVente(array $lot, array $surcharge = []): array
{
    return array_merge([
        'lignes' => ['0' => ['type' => 'lot', 'id' => (string) $lot['id'], 'quantite' => (string) $lot['quantite'], 'poids_kg' => '']],
        'remise_pct' => '0',
        'montant_paye' => '10000',
        'mode_paiement' => 'especes',
    ], $surcharge);
}

it('enregistre une vente faite hors ligne avec son numéro et son adresse', function () {
    Tarif::create(['categorie' => 'engraissement', 'mode' => 'unite', 'prix' => 5000, 'actif' => true]);
    $lot = hlLot(10);
    $action = hlAction('vente', hlVente(['id' => $lot->id, 'quantite' => 2]));

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$action]])
        ->assertJsonPath('resultats.0.statut', 'appliquee')
        ->assertJsonPath('resultats.0.url', fn ($url) => is_string($url) && str_contains($url, '/gestionnaire/ventes/'));

    $vente = Vente::firstOrFail();

    expect($vente->jeton)->toBe($action['uuid']);
    expect((float) $vente->total_net)->toBe(10000.0);
    expect($lot->fresh()->effectif_actuel)->toBe(8);

    // Renvoi : aucune seconde vente
    $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$action]])
        ->assertJsonPath('resultats.0.statut', 'appliquee');

    expect(Vente::count())->toBe(1);
    expect($lot->fresh()->effectif_actuel)->toBe(8);
});

it('refuse une vente hors ligne quand le lot a été vendu entre-temps', function () {
    Tarif::create(['categorie' => 'engraissement', 'mode' => 'unite', 'prix' => 5000, 'actif' => true]);
    $lot = hlLot(2);

    $premiere = hlAction('vente', hlVente(['id' => $lot->id, 'quantite' => 2]));
    $seconde = hlAction('vente', hlVente(['id' => $lot->id, 'quantite' => 2]));

    $reponse = $this->postJson(route('gestionnaire.synchronisation.envoyer'), ['actions' => [$premiere, $seconde]]);

    $reponse->assertJsonPath('resultats.0.statut', 'appliquee');
    $reponse->assertJsonPath('resultats.1.statut', 'refusee');
    expect(Vente::count())->toBe(1);
    $this->assertDatabaseHas('actions_synchronisees', ['uuid' => $seconde['uuid'], 'statut' => 'refusee']);
});

it('refuse une vente sans tarif', function () {
    $lot = hlLot(5);

    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('vente', hlVente(['id' => $lot->id, 'quantite' => 1]))],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    expect(Vente::count())->toBe(0);
});

// --- Supervision ---

it('prévient le propriétaire des saisies refusées', function () {
    $this->postJson(route('gestionnaire.synchronisation.envoyer'), [
        'actions' => [hlAction('depense', ['categorie' => 'salaires', 'libelle' => 'Salaire', 'montant' => '1000', 'date_charge' => today()->toDateString()], ['libelle' => 'Dépense : Salaire'])],
    ])->assertJsonPath('resultats.0.statut', 'refusee');

    $this->actingAs($this->proprietaire);

    $this->get(route('proprietaire.synchronisations'))
        ->assertOk()
        ->assertSee('Dépense : Salaire');

    $this->get(route('proprietaire.tableau_de_bord'))
        ->assertOk()
        ->assertSee('hors ligne ont été refusées');
});

it('interdit la page des saisies refusées au gestionnaire', function () {
    $this->get(route('proprietaire.synchronisations'))->assertForbidden();
});

// --- Fichiers de l'application installable ---

it("fournit la page de secours, le manifeste et le service worker", function () {
    auth()->logout();

    $this->get('/hors-ligne')->assertOk()->assertSee("n'est pas disponible hors ligne");

    $manifeste = json_decode(file_get_contents(public_path('manifest.json')), true);

    expect($manifeste['start_url'])->toBe('/gestionnaire');
    expect($manifeste['display'])->toBe('standalone');
    expect($manifeste['icons'])->toHaveCount(2);

    expect(file_get_contents(public_path('sw.js')))->toContain("addEventListener('fetch'");
});

// --- Écrans ---

it('prépare les pages du gestionnaire pour le mode hors ligne', function () {
    $this->get(route('gestionnaire.accueil'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('name="url-synchronisation"', false)
        ->assertSee('id="bandeau-sync"', false)
        ->assertSee('id="compteur-sync"', false)
        ->assertSee('data-espace="gestionnaire"', false);

    $this->get(route('gestionnaire.synchronisation.index'))
        ->assertOk()
        ->assertSee('id="liste-synchronisation"', false)
        ->assertSee('Préparer le mode hors ligne');
});

it('marque les formulaires utilisables hors ligne', function () {
    Tache::factory()->create();
    $this->get(route('gestionnaire.taches.index'))->assertOk()->assertSee('data-hors-ligne="tache_cocher"', false);

    $saillie = Saillie::factory()->create(['statut' => StatutSaillie::EnAttente]);
    $this->get(route('gestionnaire.saillies.show', $saillie))->assertOk()->assertSee('data-hors-ligne="palpation"', false);

    $this->get(route('gestionnaire.depenses.index'))->assertOk()->assertSee('data-hors-ligne="depense"', false);

    $animal = Reproducteur::factory()->create();
    $this->get(route('gestionnaire.reproducteurs.show', $animal))
        ->assertOk()
        ->assertSee('data-hors-ligne="pesee_reproducteur"', false)
        ->assertSee('data-hors-ligne="deces"', false);

    $ration = hlRation();
    Reproducteur::factory()->male()->create();
    $this->get(route('gestionnaire.alimentation.index'))->assertOk()->assertSee('data-hors-ligne="distribution"', false);

    Tarif::create(['categorie' => 'engraissement', 'mode' => 'kg', 'prix' => 2500, 'actif' => true]);
    $this->get(route('gestionnaire.ventes.create'))->assertOk()->assertSee('data-hors-ligne="vente"', false);

    $this->get(route('gestionnaire.taches.cloture'))->assertOk()->assertSee('data-attendre-synchro', false);
});

it('garde la saisie classique pour les actions qui exigent le réseau', function () {
    $saillie = Saillie::factory()->create(['statut' => StatutSaillie::MiseBas, 'date_saillie' => today()->subDays(40)]);
    Portee::create(['saillie_id' => $saillie->id, 'date_mise_bas' => today()->subDays(10), 'nes_vivants' => 6, 'morts_nes' => 0]);

    $this->get(route('gestionnaire.saillies.show', $saillie))
        ->assertOk()
        ->assertDontSee('data-hors-ligne="sevrage"', false);
});