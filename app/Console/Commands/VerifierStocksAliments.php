<?php

namespace App\Console\Commands;

use App\Services\GestionAliments;
use Illuminate\Console\Command;

class VerifierStocksAliments extends Command
{
    protected $signature = 'aliments:verifier-stocks';

    protected $description = "Prévient le propriétaire des aliments dont l'autonomie est inférieure au seuil";

    public function handle(GestionAliments $aliments): int
    {
        $envoyees = $aliments->notifierAlertes();

        $this->info("{$envoyees} alerte(s) de stock envoyée(s).");

        return self::SUCCESS;
    }
}