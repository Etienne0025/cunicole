<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VerifierProduction extends Command
{
    protected $signature = 'cunicole:verifier-production';

    protected $description = 'Vérifie que le serveur est prêt pour la mise en service (configuration, comptes, fichiers, planificateur)';

    private int $erreurs = 0;

    private int $attentions = 0;

    public function handle(): int
    {
        $this->line('Vérification de la mise en service :');
        $this->line('');

        $this->configuration();
        $this->baseDeDonnees();
        $this->comptes();
        $this->fichiers();
        $this->planificateur();

        $this->line('');

        if ($this->erreurs > 0) {
            $this->error("{$this->erreurs} problème(s) bloquant(s) à corriger avant la mise en service.");

            return self::FAILURE;
        }

        if ($this->attentions > 0) {
            $this->warn("Aucun problème bloquant, {$this->attentions} point(s) d'attention.");

            return self::SUCCESS;
        }

        $this->info('Tout est en ordre : le serveur est prêt.');

        return self::SUCCESS;
    }

    private function configuration(): void
    {
        $this->controle(
            app()->environment('production'),
            'Environnement « production »',
            'APP_ENV vaut « '.app()->environment().' » : mettez APP_ENV=production dans .env',
        );
        $this->controle(
            ! config('app.debug'),
            'Mode débogage désactivé',
            'APP_DEBUG=true montre des détails techniques aux visiteurs : mettez APP_DEBUG=false',
        );
        $this->controle(filled(config('app.key')), "Clé de l'application définie", 'lancez : php artisan key:generate');

        $url = (string) config('app.url');
        $hote = (string) parse_url($url, PHP_URL_HOST);

        $this->controle(
            str_starts_with($url, 'https://'),
            'APP_URL en HTTPS',
            "APP_URL vaut « {$url} » : indiquez l'adresse définitive en https:// (elle est inscrite dans les QR codes des cages)",
        );
        $this->controle(
            ! in_array($hote, ['', 'localhost', '127.0.0.1'], true),
            "APP_URL ne pointe pas vers l'ordinateur local",
            "APP_URL vaut « {$url} » : indiquez le nom de domaine du site",
        );
        $this->controle(
            (bool) config('session.secure'),
            'Cookies de session sécurisés',
            'ajoutez SESSION_SECURE_COOKIE=true dans .env',
        );
        $this->controle(
            config('session.driver') === 'database',
            'Sessions stockées en base de données',
            'mettez SESSION_DRIVER=database (nécessaire pour déconnecter un compte désactivé)',
            false,
        );
        $this->controle(config('app.locale') === 'fr', 'Langue : français', 'mettez APP_LOCALE=fr dans .env', false);
        $this->controle(app()->configurationIsCached(), 'Configuration en cache', 'lancez : php artisan optimize', false);

        Cache::put('verification_production', 'ok', 60);
        $this->controle(Cache::get('verification_production') === 'ok', 'Cache fonctionnel', 'vérifiez CACHE_STORE dans .env et la table cache');
    }

    private function baseDeDonnees(): void
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Connexion à la base de données');
        } catch (\Throwable $e) {
            $this->erreur('Connexion à la base de données', 'connexion impossible : vérifiez les lignes DB_ de .env');

            return;
        }

        $migrator = app('migrator');
        $depot = $migrator->getRepository();

        if (! $depot->repositoryExists()) {
            $this->erreur('Base de données à jour', 'aucune migration exécutée : lancez php artisan migrate --force');

            return;
        }

        $fichiers = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $manquantes = array_diff($fichiers, $depot->getRan());

        $this->controle(
            $manquantes === [],
            'Base de données à jour',
            count($manquantes).' migration(s) en attente : lancez php artisan migrate --force',
        );
    }

    private function comptes(): void
    {
        $proprietaires = Utilisateur::where('role', Role::Proprietaire->value)
            ->where('actif', true)
            ->where('email', 'not like', '%@cunicole.test')
            ->count();

        $this->controle(
            $proprietaires > 0,
            'Un vrai compte propriétaire actif existe',
            'aucun propriétaire : lancez php artisan cunicole:installer',
        );

        $demos = Utilisateur::where('email', 'like', '%@cunicole.test')->count();

        $this->controle(
            $demos === 0,
            'Aucun compte de démonstration',
            "{$demos} compte(s) de démonstration (mot de passe connu de tous) : lancez php artisan cunicole:retirer-demo",
        );
    }

    private function fichiers(): void
    {
        $this->controle(
            is_file(public_path('build/manifest.json')),
            'Fichiers CSS et JavaScript compilés',
            'lancez : npm ci && npm run build',
        );
        $this->controle(
            ! is_file(public_path('hot')),
            'Serveur de développement Vite arrêté',
            'supprimez le fichier public/hot : il fait charger les pages depuis un serveur absent',
        );

        foreach (['sw.js', 'manifest.json', 'icons/icone-192.png', 'icons/icone-512.png'] as $fichier) {
            $this->controle(
                is_file(public_path($fichier)),
                "Fichier public/{$fichier}",
                "fichier manquant : le mode hors ligne et l'installation sur téléphone ne fonctionneront pas",
            );
        }

        foreach (['storage' => storage_path(), 'storage/logs' => storage_path('logs'), 'bootstrap/cache' => base_path('bootstrap/cache')] as $nom => $dossier) {
            $this->controle(
                is_dir($dossier) && is_writable($dossier),
                "Dossier modifiable : {$nom}",
                'donnez les droits au serveur web : chown -R www-data:www-data storage bootstrap/cache',
            );
        }
    }

    private function planificateur(): void
    {
        $battement = Cache::get('planificateur_battement');

        if ($battement === null) {
            $this->erreur('Planificateur', "jamais exécuté : installez la tâche cron (deploy/cron-cunicole), puis attendez une minute");

            return;
        }

        $minutes = abs((int) Carbon::parse($battement)->diffInMinutes(now()));

        $this->controle(
            $minutes <= 3,
            "Planificateur actif (dernier passage il y a {$minutes} min)",
            "dernier passage il y a {$minutes} min : vérifiez la tâche cron (checklist du jour et alertes en dépendent)",
        );
    }

    private function controle(bool $reussi, string $libelle, string $conseil, bool $bloquant = true): void
    {
        if ($reussi) {
            $this->ok($libelle);
        } elseif ($bloquant) {
            $this->erreur($libelle, $conseil);
        } else {
            $this->attention($libelle, $conseil);
        }
    }

    private function ok(string $libelle): void
    {
        $this->line("  <fg=green>[OK]</>        {$libelle}");
    }

    private function attention(string $libelle, string $conseil): void
    {
        $this->attentions++;
        $this->line("  <fg=yellow>[ATTENTION]</> {$libelle} : {$conseil}");
    }

    private function erreur(string $libelle, string $conseil): void
    {
        $this->erreurs++;
        $this->line("  <fg=red>[ERREUR]</>    {$libelle} : {$conseil}");
    }
}