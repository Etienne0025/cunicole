<?php

namespace App\Console\Commands;

use App\Models\Utilisateur;
use App\Services\GestionComptes;
use Illuminate\Console\Command;

class ReinitialiserMotDePasse extends Command
{
    protected $signature = 'comptes:reinitialiser-mot-de-passe {email : Adresse email du compte}';

    protected $description = "Génère un mot de passe provisoire pour un compte (si le propriétaire a perdu son accès)";

    public function handle(GestionComptes $comptes): int
    {
        $utilisateur = Utilisateur::where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($utilisateur === null) {
            $this->error('Aucun compte ne correspond à cette adresse.');

            return self::FAILURE;
        }

        $motDePasse = $comptes->reinitialiserMotDePasse($utilisateur);

        $this->info("Compte : {$utilisateur->nom_complet} ({$utilisateur->email})");
        $this->info("Mot de passe provisoire : {$motDePasse}");
        $this->line('Il devra être changé à la prochaine connexion.');

        return self::SUCCESS;
    }
}