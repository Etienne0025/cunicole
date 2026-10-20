<?php

namespace Database\Factories;

use App\Enums\OrigineReproducteur;
use App\Enums\SexeAnimal;
use App\Enums\StatutReproducteur;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReproducteurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'matricule' => strtoupper(fake()->unique()->bothify('??-####')),
            'nom' => null,
            'sexe' => SexeAnimal::Femelle,
            'race' => fake()->randomElement(['Néo-Zélandais', 'Californien', 'Géant des Flandres', 'Locale']),
            'date_naissance' => now()->subMonths(8)->toDateString(),
            'origine' => OrigineReproducteur::Ferme,
            'statut' => StatutReproducteur::Actif,
            'date_entree' => now()->subMonths(2)->toDateString(),
            'valide_le' => now(),
        ];
    }

    public function male(): static
    {
        return $this->state(fn () => ['sexe' => SexeAnimal::Male]);
    }
}