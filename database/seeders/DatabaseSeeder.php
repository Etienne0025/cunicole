<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Utilisateur;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Comptes et données de démonstration : développement uniquement.
        // Sur le serveur, le premier propriétaire se crée avec : php artisan cunicole:installer
        if (! app()->environment('local')) {
            $this->command?->warn('Environnement non local : aucun compte de démonstration créé.');

            return;
        }

        Utilisateur::updateOrCreate(['email' => 'proprietaire@cunicole.test'], [
            'nom' => 'ADJOVI',
            'prenom' => 'Koffi',
            'password' => 'password',
            'role' => Role::Proprietaire,
        ]);

        Utilisateur::updateOrCreate(['email' => 'gestionnaire@cunicole.test'], [
            'nom' => 'HOUNTON',
            'prenom' => 'Marius',
            'password' => 'password',
            'role' => Role::Gestionnaire,
        ]);

        $this->call(DonneesDemoSeeder::class);
    }
}