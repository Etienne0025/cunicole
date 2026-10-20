<?php

namespace App\Providers;

use App\Models\Cage;
use App\Models\Distribution;
use App\Models\LotEngraissement;
use App\Models\PeseeLot;
use App\Models\ProduitVeterinaire;
use App\Models\ProtocoleVaccinal;
use App\Models\Ration;
use App\Models\Reproducteur;
use App\Models\Saillie;
use App\Models\Traitement;
use App\Models\Utilisateur;
use App\Models\Vaccination;
use App\Services\GestionTaches;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Production : toutes les adresses générées (liens, redirections, QR codes des cages)
        // utilisent l'adresse définitive du site, en HTTPS
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }

        // Garde-fou : interdit migrate:fresh, migrate:refresh, migrate:reset et db:wipe sur le serveur,
        // qui effaceraient toutes les données de la ferme
        try {
            DB::prohibitDestructiveCommands($this->app->isProduction());
        } catch (\Throwable $e) {
            // Méthode absente dans cette version de Laravel : le garde-fou n'est simplement pas actif
        }

        Route::resourceVerbs([
            'create' => 'creer',
            'edit' => 'modifier',
        ]);

        // Noms courts stockés en base pour les relations polymorphes (tâches, vaccinations, traitements)
        Relation::morphMap([
            'saillie' => Saillie::class,
            'cage' => Cage::class,
            'lot' => LotEngraissement::class,
            'reproducteur' => Reproducteur::class,
            'traitement' => Traitement::class,
            'ration' => Ration::class,
        ]);

        // Dès qu'une action est enregistrée, la tâche correspondante se termine avec son horodatage
        Saillie::updated(fn (Saillie $saillie) => app(GestionTaches::class)->resoudre($saillie));
        Cage::updated(fn (Cage $cage) => app(GestionTaches::class)->resoudre($cage));
        LotEngraissement::updated(fn (LotEngraissement $lot) => app(GestionTaches::class)->resoudre($lot));
        Reproducteur::updated(fn (Reproducteur $reproducteur) => app(GestionTaches::class)->resoudre($reproducteur));
        Ration::updated(fn (Ration $ration) => app(GestionTaches::class)->resoudre($ration));
        PeseeLot::created(fn (PeseeLot $pesee) => app(GestionTaches::class)->resoudre($pesee->lot));

        Vaccination::created(function (Vaccination $vaccination) {
            if ($vaccination->vaccinable) {
                app(GestionTaches::class)->resoudre($vaccination->vaccinable);
            }
        });

        Distribution::created(function (Distribution $distribution) {
            if ($distribution->ration) {
                app(GestionTaches::class)->resoudre($distribution->ration);
            }
        });

        // Données du bloc « Santé » affiché sur les fiches d'animal et de lot
        View::composer('gestionnaire.sante._bloc', function ($vue) {
            $donnees = $vue->getData();
            $cible = $donnees['cible'];

            $vue->with([
                'produitsSoins' => ProduitVeterinaire::where('actif', true)
                    ->whereIn('type', ['traitement', 'autre'])->orderBy('nom')->get(),
                'protocolesActifs' => ProtocoleVaccinal::where('actif', true)
                    ->where('cible', $donnees['type'])->orderBy('nom')->get(),
                'vaccinations' => $cible->vaccinations()->with('protocole')
                    ->latest('date_vaccination')->latest('id')->limit(10)->get(),
                'traitements' => $cible->traitements()
                    ->latest('date_debut')->latest('id')->limit(10)->get(),
                'bloquant' => $cible->traitementBloquant(),
            ]);
        });

        // Mémorise la dernière connexion de chaque compte
        Event::listen(Login::class, function (Login $evenement) {
            if ($evenement->user instanceof Utilisateur) {
                $evenement->user->forceFill(['derniere_connexion_le' => now()])->saveQuietly();
            }
        });
    }
}