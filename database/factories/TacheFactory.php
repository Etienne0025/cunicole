<?php

namespace Database\Factories;

use App\Enums\StatutTache;
use App\Enums\TypeTache;
use Illuminate\Database\Eloquent\Factories\Factory;

class TacheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cle_unique' => fake()->unique()->uuid(),
            'type' => TypeTache::Quotidienne,
            'date_prevue' => today(),
            'titre' => fake()->sentence(3),
            'statut' => StatutTache::AFaire,
        ];
    }

    public function faite(): static
    {
        return $this->state(fn () => ['statut' => StatutTache::Faite, 'faite_le' => now()]);
    }
}