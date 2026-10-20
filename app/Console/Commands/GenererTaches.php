<?php

namespace App\Console\Commands;

use App\Services\GestionTaches;
use Illuminate\Console\Command;

class GenererTaches extends Command
{
    protected $signature = 'taches:generer';

    protected $description = "Génère la checklist du jour (palpations, sevrages, nettoyages, pesées, tâches quotidiennes)";

    public function handle(GestionTaches $taches): int
    {
        $creees = $taches->synchroniser();

        $this->info("{$creees} tâche(s) créée(s) pour aujourd'hui.");

        return self::SUCCESS;
    }
}