<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Utilisateur> */
class UtilisateurFactory extends Factory
{
    protected $model = Utilisateur::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => str()->random(10),
            'role' => Role::Gestionnaire,
            'actif' => true,
            'doit_changer_mot_de_passe' => false,
        ];
    }

    public function proprietaire(): static
    {
        return $this->state(fn () => ['role' => Role::Proprietaire]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
