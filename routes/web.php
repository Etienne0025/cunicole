<?php
use App\Http\Controllers\Proprietaire\SynchronisationController;
use App\Http\Controllers\Gestionnaire;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Proprietaire;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecuVenteController;
use App\Http\Controllers\ChangementMotDePasseController;



// Mode hors ligne : accessibles sans connexion (le service worker les met en cache)
Route::get('/ping', fn () => response()->noContent()->header('Cache-Control', 'no-store'))->name('ping');
Route::view('/hors-ligne', 'hors-ligne')->name('hors-ligne');
Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name' => config('app.name').' : élevage cunicole',
        'short_name' => config('app.name'),
        'description' => "Suivi de l'élevage de lapins : cages, reproduction, santé, alimentation, ventes.",
        'lang' => 'fr',
        'start_url' => '/accueil',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#f3f4f6',
        'theme_color' => '#047857',
        'icons' => [
            ['src' => '/icons/icone-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icone-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icone-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
})->name('manifest');


Route::get('/', fn () => redirect()->route('login'));

Route::middleware('auth')->group(function () {
    // Redirection automatique selon le rôle
    Route::get('/accueil', fn () => redirect()->route(auth()->user()->role->routeAccueil()))
        ->name('accueil');

    Route::get('/mot-de-passe/changer', [ChangementMotDePasseController::class, 'edit'])->name('mot-de-passe.changer');
Route::put('/mot-de-passe/changer', [ChangementMotDePasseController::class, 'update'])->name('mot-de-passe.mettre_a_jour');

    // Routes du profil (Breeze) : on garde les noms, on met l'URL en français
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
   
});

Route::middleware(['auth', 'role:gestionnaire', 'hors-ligne'])
    ->prefix('gestionnaire')->name('gestionnaire.')
    ->group(function () {

        Route::get('synchronisation', [Gestionnaire\SynchronisationController::class, 'index'])->name('synchronisation.index');
Route::get('synchronisation/jeton', [Gestionnaire\SynchronisationController::class, 'jeton'])->name('synchronisation.jeton');
        Route::get('/', Gestionnaire\AccueilController::class)->name('accueil');
    });

Route::middleware(['auth', 'role:proprietaire'])
    ->prefix('proprietaire')->name('proprietaire.')
    ->group(function () {
        Route::get('/', Proprietaire\TableauDeBordController::class)->name('tableau_de_bord');
    });

    Route::middleware(['auth', 'role:gestionnaire'])
    ->prefix('gestionnaire')->name('gestionnaire.')
    ->group(function () {
        Route::get('/', Gestionnaire\AccueilController::class)->name('accueil');

        Route::resource('batiments', Gestionnaire\BatimentController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        Route::post('cages/planche-qr', [Gestionnaire\CageController::class, 'planche'])->name('cages.planche');
        Route::post('cages/{cage}/activer', [Gestionnaire\CageController::class, 'activer'])->name('cages.activer');
        Route::post('cages/{cage}/statut', [Gestionnaire\CageController::class, 'changerStatut'])->name('cages.statut');
        Route::resource('cages', Gestionnaire\CageController::class);
        Route::post('reproducteurs/{reproducteur}/deplacer', [Gestionnaire\ReproducteurController::class, 'deplacer'])->name('reproducteurs.deplacer');
Route::post('reproducteurs/{reproducteur}/sortie', [Gestionnaire\ReproducteurController::class, 'sortie'])->name('reproducteurs.sortie');
Route::post('reproducteurs/{reproducteur}/pesees', [Gestionnaire\PeseeController::class, 'store'])->name('reproducteurs.pesees.store');

Route::resource('reproducteurs', Gestionnaire\ReproducteurController::class)->except(['destroy']);

Route::get('elevage', Gestionnaire\ElevageController::class)->name('elevage');

Route::post('saillies/{saillie}/palpation', [Gestionnaire\SaillieController::class, 'palpation'])->name('saillies.palpation');
Route::post('saillies/{saillie}/boite-a-nids', [Gestionnaire\SaillieController::class, 'boiteANids'])->name('saillies.boite_nids');
Route::post('saillies/{saillie}/mise-bas', [Gestionnaire\PorteeController::class, 'miseBas'])->name('saillies.mise_bas');
Route::post('saillies/{saillie}/sevrage', [Gestionnaire\PorteeController::class, 'sevrage'])->name('saillies.sevrage');
Route::resource('saillies', Gestionnaire\SaillieController::class)
    ->only(['index', 'create', 'store', 'show'])
    ->parameters(['saillies' => 'saillie']);

Route::post('lots/{lot}/pesees', [Gestionnaire\LotController::class, 'peser'])->name('lots.pesees.store');
Route::post('lots/{lot}/convertir', [Gestionnaire\LotController::class, 'convertir'])->name('lots.convertir');
Route::resource('lots', Gestionnaire\LotController::class)
    ->only(['index', 'show'])
    ->parameters(['lots' => 'lot']);

Route::get('taches', [Gestionnaire\TacheController::class, 'index'])->name('taches.index');
Route::get('taches/cloture', [Gestionnaire\TacheController::class, 'cloture'])->name('taches.cloture');
Route::post('taches/cloture', [Gestionnaire\TacheController::class, 'cloturer'])->name('taches.cloturer');
Route::post('taches/{tache}/cocher', [Gestionnaire\TacheController::class, 'cocher'])->name('taches.cocher');
Route::post('taches/{tache}/annuler', [Gestionnaire\TacheController::class, 'decocher'])->name('taches.decocher');


Route::get('plus', Gestionnaire\PlusController::class)->name('plus');

Route::get('sante', [Gestionnaire\SanteController::class, 'index'])->name('sante.index');
Route::post('sante/traitements', [Gestionnaire\SanteController::class, 'traiter'])->name('sante.traiter');
Route::post('sante/vaccinations-anterieures', [Gestionnaire\SanteController::class, 'vaccinationAnterieure'])->name('sante.vaccination_anterieure');
Route::post('sante/deces', [Gestionnaire\SanteController::class, 'deces'])->name('sante.deces');

Route::get('alimentation', [Gestionnaire\AlimentationController::class, 'index'])->name('alimentation.index');
Route::post('alimentation/rations/{ration}/distribuer', [Gestionnaire\AlimentationController::class, 'distribuer'])->name('alimentation.distribuer');
Route::post('alimentation/receptions', [Gestionnaire\AlimentationController::class, 'recevoir'])->name('alimentation.recevoir');
Route::get('ventes', [Gestionnaire\VenteController::class, 'index'])->name('ventes.index');
Route::get('ventes/nouvelle', [Gestionnaire\VenteController::class, 'create'])->name('ventes.create');
Route::post('ventes', [Gestionnaire\VenteController::class, 'store'])->name('ventes.store');
Route::get('ventes/{vente}', [Gestionnaire\VenteController::class, 'show'])->name('ventes.show')->whereNumber('vente');
Route::post('ventes/{vente}/paiements', [Gestionnaire\VenteController::class, 'paiement'])->name('ventes.paiements.store')->whereNumber('vente');
Route::get('ventes/{vente}/recu', RecuVenteController::class)->name('ventes.recu')->whereNumber('vente');

Route::get('depenses', [Gestionnaire\ChargeController::class, 'index'])->name('depenses.index');
Route::post('depenses', [Gestionnaire\ChargeController::class, 'store'])->name('depenses.store');
Route::delete('depenses/{charge}', [Gestionnaire\ChargeController::class, 'destroy'])->name('depenses.destroy')->whereNumber('charge');


});

Route::middleware(['auth', 'role:proprietaire'])
    ->prefix('proprietaire')->name('proprietaire.')
    ->group(function () {
        Route::get('/', Proprietaire\TableauDeBordController::class)->name('tableau_de_bord');
        Route::get('/cartographie', Proprietaire\CartographieController::class)->name('cartographie');
   Route::get('/cheptel', Proprietaire\CheptelController::class)->name('cheptel');
    Route::get('/reproduction', [Proprietaire\ReproductionController::class, 'index'])->name('reproduction');
Route::post('/reproduction/objectif', [Proprietaire\ReproductionController::class, 'objectif'])->name('reproduction.objectif');
Route::get('/reproduction/femelles/{reproducteur}', [Proprietaire\ReproductionController::class, 'femelle'])->name('reproduction.femelle');   

Route::get('/selection', [Proprietaire\SelectionController::class, 'index'])->name('selection');
Route::put('/selection/criteres', [Proprietaire\SelectionController::class, 'criteres'])->name('selection.criteres');
Route::post('/selection/{reproducteur}/valider', [Proprietaire\SelectionController::class, 'valider'])->name('selection.valider');
Route::post('/selection/{reproducteur}/refuser', [Proprietaire\SelectionController::class, 'refuser'])->name('selection.refuser');

Route::get('/supervision', [Proprietaire\SupervisionController::class, 'index'])->name('supervision');
Route::get('/supervision/progression', [Proprietaire\SupervisionController::class, 'progression'])->name('supervision.progression');
Route::get('/rapports', [Proprietaire\RapportController::class, 'index'])->name('rapports.index');
Route::get('/rapports/{date}', [Proprietaire\RapportController::class, 'show'])
    ->where('date', '\d{4}-\d{2}-\d{2}')->name('rapports.show');


Route::get('/sante', [Proprietaire\SanteController::class, 'index'])->name('sante');
Route::post('/sante/alertes/lues', [Proprietaire\SanteController::class, 'alertesLues'])->name('sante.alertes_lues');

Route::get('/pharmacie', [Proprietaire\PharmacieController::class, 'index'])->name('pharmacie');
Route::post('/pharmacie/produits', [Proprietaire\PharmacieController::class, 'storeProduit'])->name('pharmacie.produits.store');
Route::put('/pharmacie/produits/{produit}', [Proprietaire\PharmacieController::class, 'updateProduit'])->name('pharmacie.produits.update');
Route::post('/pharmacie/produits/{produit}/achats', [Proprietaire\PharmacieController::class, 'achat'])->name('pharmacie.achats.store');
Route::post('/pharmacie/produits/{produit}/ajustements', [Proprietaire\PharmacieController::class, 'ajustement'])->name('pharmacie.ajustements.store');

Route::get('/calendrier-prophylactique', [Proprietaire\ProtocoleVaccinalController::class, 'index'])->name('protocoles.index');
Route::post('/calendrier-prophylactique', [Proprietaire\ProtocoleVaccinalController::class, 'store'])->name('protocoles.store');
Route::put('/calendrier-prophylactique/{protocole}', [Proprietaire\ProtocoleVaccinalController::class, 'update'])->name('protocoles.update');
Route::get('/aliments', [Proprietaire\AlimentController::class, 'index'])->name('aliments');
Route::post('/aliments', [Proprietaire\AlimentController::class, 'store'])->name('aliments.store');
Route::post('/aliments/alertes/lues', [Proprietaire\AlimentController::class, 'alertesLues'])->name('aliments.alertes_lues');
Route::put('/aliments/{aliment}', [Proprietaire\AlimentController::class, 'update'])->name('aliments.update');
Route::post('/aliments/{aliment}/ajustements', [Proprietaire\AlimentController::class, 'ajustement'])->name('aliments.ajustements.store');

Route::get('/rations', [Proprietaire\RationController::class, 'index'])->name('rations.index');
Route::post('/rations', [Proprietaire\RationController::class, 'store'])->name('rations.store');
Route::put('/rations/{ration}', [Proprietaire\RationController::class, 'update'])->name('rations.update');

Route::get('/consommation-aliments', Proprietaire\ConsommationController::class)->name('consommation');

Route::get('/finances', [Proprietaire\FinanceController::class, 'index'])->name('finances');

Route::get('/ventes', [Proprietaire\VenteController::class, 'index'])->name('ventes.index');
Route::get('/ventes/{vente}', [Proprietaire\VenteController::class, 'show'])->name('ventes.show')->whereNumber('vente');
Route::get('/ventes/{vente}/recu', RecuVenteController::class)->name('ventes.recu')->whereNumber('vente');

Route::get('/charges', [Proprietaire\ChargeController::class, 'index'])->name('charges.index');
Route::post('/charges', [Proprietaire\ChargeController::class, 'store'])->name('charges.store');
Route::delete('/charges/{charge}', [Proprietaire\ChargeController::class, 'destroy'])->name('charges.destroy')->whereNumber('charge');

Route::get('/tarifs', [Proprietaire\TarifController::class, 'index'])->name('tarifs');
Route::put('/tarifs/parametres', [Proprietaire\TarifController::class, 'parametres'])->name('tarifs.parametres');
Route::put('/tarifs/{categorie}', [Proprietaire\TarifController::class, 'tarif'])
    ->where('categorie', 'engraissement|reproducteur|reforme')->name('tarifs.update');
Route::post('/notifications/lues', [Proprietaire\TableauDeBordController::class, 'notificationsLues'])->name('notifications.lues');

Route::resource('utilisateurs', Proprietaire\UtilisateurController::class)
    ->only(['index', 'create', 'store', 'edit', 'update'])
    ->parameters(['utilisateurs' => 'utilisateur']);
Route::post('/utilisateurs/{utilisateur}/desactiver', [Proprietaire\UtilisateurController::class, 'desactiver'])->name('utilisateurs.desactiver');
Route::post('/utilisateurs/{utilisateur}/reactiver', [Proprietaire\UtilisateurController::class, 'reactiver'])->name('utilisateurs.reactiver');
Route::post('/utilisateurs/{utilisateur}/mot-de-passe', [Proprietaire\UtilisateurController::class, 'motDePasse'])->name('utilisateurs.mot_de_passe');
Route::get('synchronisations', [SynchronisationController::class, 'index'])
    ->name('synchronisations');

});

require __DIR__.'/auth.php';
