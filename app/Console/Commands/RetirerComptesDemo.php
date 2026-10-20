<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Utilisateur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetirerComptesDemo extends Command
{
    protected $signature = 'cunicole:retirer-demo {--force : Supprimer sans demander de confirmation}';

    protected $description = 'Supprime les comptes de démonstration (@cunicole.test) avant la mise en service';

    public function handle(): int
    {
        $demos = Utilisateur::where('email', 'like', '%@cunicole.test')->get();

        if ($demos->isEmpty()) {
            $this->info('Aucun compte de démonstration à retirer.');

            return self::SUCCESS;
        }

        $vraiProprietaire = Utilisateur::where('role', Role::Proprietaire->value)
            ->where('actif', true)
            ->where('email', 'not like', '%@cunicole.test')
            ->exists();

        if (! $vraiProprietaire) {
            $this->error("Créez d'abord le vrai compte propriétaire (php artisan cunicole:installer) : sinon plus personne ne pourrait se connecter.");

            return self::FAILURE;
        }

        foreach ($demos as $demo) {
            $this->line(" - {$demo->email}");
        }

        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces comptes ?', false)) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($demos) {
            foreach ($demos as $demo) {
                $demo->notifications()->delete();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $demo->id)->delete();
                }

                // Les saisies liées à ces comptes (nom du vendeur, du gestionnaire...) sont conservées, sans nom
                $demo->delete();
            }
        });

        $this->info($demos->count().' compte(s) de démonstration supprimé(s).');

        return self::SUCCESS;
    }
}