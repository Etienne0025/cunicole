<?php

namespace Database\Factories;

use App\Enums\StatutSaillie;
use App\Models\Reproducteur;
use App\Models\Saillie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Saillie>
 */
class SaillieFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'femelle_id' => Reproducteur::factory(),
        'male_id' => Reproducteur::factory()->male(),
        'date_saillie' => today(),
        'statut' => StatutSaillie::EnAttente,
    ];
}
}
