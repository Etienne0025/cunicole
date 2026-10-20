<?php

use App\Enums\Role;
use App\Models\Utilisateur;
use App\Services\GestionComptes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->proprietaire = Utilisateur::factory()->proprietaire()->create();
    $this->gestionnaire = Utilisateur::factory()->create();
});

function donneesCompte(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Dossou', 'prenom' => 'Marie', 'email' => 'marie@cunicole.test',
        'telephone' => '97000000', 'role' => 'gestionnaire',
    ], $surcharge);
}

// --- Création ---

it("crée un compte avec un mot de passe provisoire affiché une seule fois", function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.store'), donneesCompte())
        ->assertRedirect(route('proprietaire.utilisateurs.index'))
        ->assertSessionHas('identifiants');

    $compte = Utilisateur::where('email', 'marie@cunicole.test')->firstOrFail();
    $identifiants = session('identifiants');

    expect($compte->role)->toBe(Role::Gestionnaire);
    expect($compte->actif)->toBeTrue();
    expect($compte->doit_changer_mot_de_passe)->toBeTrue();
    expect(Hash::check($identifiants['mot_de_passe'], $compte->password))->toBeTrue();
    expect(strlen($identifiants['mot_de_passe']))->toBeGreaterThanOrEqual(10);
});

it("met l'adresse email en minuscules et refuse un doublon", function () {
    $this->actingAs($this->proprietaire);

    $this->post(route('proprietaire.utilisateurs.store'), donneesCompte(['email' => 'MARIE@Cunicole.Test']))
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('utilisateurs', ['email' => 'marie@cunicole.test']);

    $this->post(route('proprietaire.utilisateurs.store'), donneesCompte(['prenom' => 'Autre']))
        ->assertSessionHasErrors('email');
});

it('refuse un rôle inconnu', function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.store'), donneesCompte(['role' => 'administrateur']))
        ->assertSessionHasErrors('role');
});

it('génère des mots de passe provisoires lisibles et robustes', function () {
    $comptes = app(GestionComptes::class);

    foreach (range(1, 50) as $_) {
        $mot = $comptes->genererMotDePasse();

        expect($mot)->toMatch('/[a-z]/')->toMatch('/[A-Z]/')->toMatch('/\d/');
        expect($mot)->not->toMatch('/[0OIl1]/');
        expect(strlen($mot))->toBe(10);
    }
});

// --- Première connexion et désactivation ---

it("force le changement du mot de passe provisoire à la première connexion", function () {
    $compte = Utilisateur::factory()->create(['doit_changer_mot_de_passe' => true]);

    $this->post(route('login'), ['email' => $compte->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($compte);

    $this->get(route('gestionnaire.accueil'))->assertRedirect(route('mot-de-passe.changer'));
    $this->get(route('mot-de-passe.changer'))->assertOk();

    $this->put(route('mot-de-passe.mettre_a_jour'), [
        'current_password' => 'password',
        'password' => 'NouveauMdp2026',
        'password_confirmation' => 'NouveauMdp2026',
    ])->assertRedirect(route('accueil'));

    expect($compte->fresh()->doit_changer_mot_de_passe)->toBeFalse();
    expect(Hash::check('NouveauMdp2026', $compte->fresh()->password))->toBeTrue();

    $this->get(route('gestionnaire.accueil'))->assertOk();
});

it('refuse de garder le mot de passe provisoire', function () {
    $compte = Utilisateur::factory()->create(['doit_changer_mot_de_passe' => true]);

    $this->actingAs($compte)
        ->put(route('mot-de-passe.mettre_a_jour'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

    expect($compte->fresh()->doit_changer_mot_de_passe)->toBeTrue();
});

it("refuse la connexion d'un compte désactivé", function () {
    $compte = Utilisateur::factory()->inactif()->create();

    $this->post(route('login'), ['email' => $compte->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it("enregistre la date de dernière connexion", function () {
    $compte = Utilisateur::factory()->create();

    expect($compte->derniere_connexion_le)->toBeNull();

    $this->post(route('login'), ['email' => $compte->email, 'password' => 'password']);

    expect($compte->fresh()->derniere_connexion_le)->not->toBeNull();
});

it("désactive un compte et le déconnecte à sa prochaine requête", function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.desactiver', $this->gestionnaire))
        ->assertSessionHasNoErrors();

    expect($this->gestionnaire->fresh()->actif)->toBeFalse();

    $this->actingAs($this->gestionnaire->fresh())
        ->get(route('gestionnaire.accueil'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('réactive un compte désactivé', function () {
    $compte = Utilisateur::factory()->inactif()->create();

    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.reactiver', $compte))
        ->assertSessionHasNoErrors();

    expect($compte->fresh()->actif)->toBeTrue();
});

// --- Garde-fous ---

it('interdit de désactiver son propre compte', function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.desactiver', $this->proprietaire))
        ->assertSessionHasErrors('action');

    expect($this->proprietaire->fresh()->actif)->toBeTrue();
});

it('interdit de modifier son propre rôle', function () {
    $this->actingAs($this->proprietaire)
        ->put(route('proprietaire.utilisateurs.update', $this->proprietaire), donneesCompte([
            'email' => $this->proprietaire->email, 'role' => 'gestionnaire',
        ]))->assertSessionHasErrors('role');

    expect($this->proprietaire->fresh()->role)->toBe(Role::Proprietaire);
});

it('garde toujours un propriétaire actif', function () {
    expect(fn () => app(GestionComptes::class)->desactiver($this->proprietaire, $this->gestionnaire))
        ->toThrow(ValidationException::class);

    expect($this->proprietaire->fresh()->actif)->toBeTrue();
});

it('permet de désactiver un second propriétaire', function () {
    $second = Utilisateur::factory()->proprietaire()->create();

    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.desactiver', $second))
        ->assertSessionHasNoErrors();

    expect($second->fresh()->actif)->toBeFalse();
});

// --- Modification et réinitialisation ---

it("modifie un compte et conserve son adresse email", function () {
    $this->actingAs($this->proprietaire)
        ->put(route('proprietaire.utilisateurs.update', $this->gestionnaire), donneesCompte([
            'email' => $this->gestionnaire->email, 'nom' => 'Nouveau Nom', 'role' => 'proprietaire',
        ]))->assertSessionHasNoErrors();

    $compte = $this->gestionnaire->fresh();

    expect($compte->nom)->toBe('Nouveau Nom');
    expect($compte->role)->toBe(Role::Proprietaire);
});

it('réinitialise le mot de passe et impose son changement', function () {
    $ancien = $this->gestionnaire->password;

    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.mot_de_passe', $this->gestionnaire))
        ->assertRedirect(route('proprietaire.utilisateurs.index'))
        ->assertSessionHas('identifiants');

    $compte = $this->gestionnaire->fresh();

    expect($compte->password)->not->toBe($ancien);
    expect($compte->doit_changer_mot_de_passe)->toBeTrue();
    expect(Hash::check(session('identifiants')['mot_de_passe'], $compte->password))->toBeTrue();
});

it('interdit de réinitialiser son propre mot de passe depuis la liste', function () {
    $this->actingAs($this->proprietaire)
        ->post(route('proprietaire.utilisateurs.mot_de_passe', $this->proprietaire))
        ->assertSessionHasErrors('action');
});

it('récupère un accès perdu avec la commande serveur', function () {
    $this->artisan('comptes:reinitialiser-mot-de-passe', ['email' => $this->proprietaire->email])
        ->assertSuccessful();

    expect($this->proprietaire->fresh()->doit_changer_mot_de_passe)->toBeTrue();

    $this->artisan('comptes:reinitialiser-mot-de-passe', ['email' => 'inconnu@cunicole.test'])
        ->assertFailed();
});

// --- Accès ---

it('sépare la gestion des comptes du gestionnaire', function () {
    $this->actingAs($this->gestionnaire);

    $this->get(route('proprietaire.utilisateurs.index'))->assertForbidden();
    $this->get(route('proprietaire.utilisateurs.create'))->assertForbidden();
    $this->post(route('proprietaire.utilisateurs.store'), donneesCompte())->assertForbidden();
});

it('affiche les écrans de gestion des comptes au propriétaire', function () {
    $this->actingAs($this->proprietaire);

    $this->get(route('proprietaire.utilisateurs.index'))->assertOk()->assertSee($this->gestionnaire->email);
    $this->get(route('proprietaire.utilisateurs.create'))->assertOk();
    $this->get(route('proprietaire.utilisateurs.edit', $this->gestionnaire))->assertOk();
});

it('supprime la suppression de compte et la réinitialisation par email', function () {
    $this->actingAs($this->gestionnaire)->delete('/profil')->assertStatus(405);

    auth()->logout();

    $this->get('/forgot-password')->assertNotFound();
});