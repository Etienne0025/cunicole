<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BatimentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Bâtiment '.fake()->unique()->lexify('????'),
        ];
    }
}