<?php

namespace Database\Factories;

use App\Enums\StatutCage;
use App\Enums\TypeCage;
use App\Models\Batiment;
use Illuminate\Database\Eloquent\Factories\Factory;

class CageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'batiment_id' => Batiment::factory(),
            'code' => strtoupper(fake()->unique()->bothify('?-###')),
            'type' => TypeCage::Femelle,
            'capacite' => 1,
            'statut' => StatutCage::Vide,
        ];
    }

    public function activee(): static
    {
        return $this->state(fn () => ['activee_le' => now()]);
    }

    public function occupee(): static
    {
        return $this->state(fn () => ['statut' => StatutCage::Occupee, 'activee_le' => now()]);
    }
}