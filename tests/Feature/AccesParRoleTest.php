<?php

use App\Models\Utilisateur;

it('redirige chaque rôle vers son espace', function () {
    $this->actingAs(Utilisateur::factory()->proprietaire()->create())
        ->get('/accueil')->assertRedirect(route('proprietaire.tableau_de_bord'));

    $this->actingAs(Utilisateur::factory()->create())
        ->get('/accueil')->assertRedirect(route('gestionnaire.accueil'));
});

it("interdit l'espace propriétaire au gestionnaire", function () {
    $this->actingAs(Utilisateur::factory()->create())
        ->get(route('proprietaire.tableau_de_bord'))->assertForbidden();
});

it("interdit l'espace gestionnaire au propriétaire", function () {
    $this->actingAs(Utilisateur::factory()->proprietaire()->create())
        ->get(route('gestionnaire.accueil'))->assertForbidden();
});
it('bloque un compte désactivé', function () {
    $this->actingAs(Utilisateur::factory()->inactif()->create())
        ->get(route('gestionnaire.accueil'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});