<?php

return [
    'reproduction' => [
        // Jours comptés à partir de la date de saillie
        'jours_palpation' => 12,
        'jours_boite_nids' => 28,
        'jours_mise_bas' => 31,
        'jours_sevrage' => 60,

        'age_min_saillie_mois' => 5,
        'consanguinite_etendue' => false,   // true : grands-parents, oncles/tantes, cousins

        // Alerte du propriétaire : taux d'échec des saillies sur 90 jours
        'seuil_echec_saillies' => 30,       // en %
        'echantillon_min_echec' => 5,       // nombre minimal de saillies palpées avant d'alerter
    ],

    'selection' => [
    'age_reforme_mois' => 30,   // au-delà, un reproducteur est signalé « à renouveler »
],

'taches' => [
    // Une tâche de pesée est créée si le lot n'a pas été pesé depuis ce nombre de jours
    'jours_entre_pesees_lot' => 7,

    // Tâches cochées chaque jour à la main (modifiez cette liste selon votre ferme)
    'quotidiennes' => [
        "Contrôler l'eau et les abreuvoirs",
        'Ronde de surveillance sanitaire',
        'Vérifier la propreté des allées',
    ],
],

'sante' => [
    'seuil_mortalite_pct' => 5,        // alerte si le taux est strictement supérieur
    'periode_mortalite_jours' => 30,
],

'alimentation' => [
    'jours_autonomie_alerte' => 3,   // alerte si l'autonomie est strictement inférieure à ce nombre de jours
    'ecart_alerte_pct' => 20,        // écart réel/théorique signalé comme anomalie au propriétaire
],

'devise' => [
    'symbole' => 'FCFA',
    'decimales' => 0,
],

// En-tête des reçus
'ferme' => [
    'nom' => env('FERME_NOM', 'Ferme cunicole'),
    'contact' => env('FERME_CONTACT'),
],


'hors_ligne' => [
    // Actions du gestionnaire utilisables sans réseau (nom de route => libellé affiché)
    'routes' => [
        'gestionnaire.taches.cocher' => 'Tâche marquée comme faite',
        'gestionnaire.alimentation.distribuer' => "Distribution d'aliment",
        'gestionnaire.reproducteurs.pesees.store' => 'Pesée de reproducteur',
        'gestionnaire.lots.pesees.store' => 'Pesée de lot',
        'gestionnaire.saillies.palpation' => 'Résultat de palpation',
        'gestionnaire.saillies.boite_nids' => 'Boîte à nids installée',
        'gestionnaire.saillies.mise_bas' => 'Mise bas',
        'gestionnaire.sante.deces' => 'Décès déclaré',
        'gestionnaire.depenses.store' => 'Dépense',
        'gestionnaire.ventes.store' => 'Vente',
    ],

    // Pages préparées sur le téléphone, avec les fiches qu'elles contiennent (saillies, lots, animaux, cages)
    'pages' => [
        'gestionnaire.accueil',
        'gestionnaire.taches.index',
        'gestionnaire.elevage',
        'gestionnaire.saillies.index',
        'gestionnaire.reproducteurs.index',
        'gestionnaire.lots.index',
        'gestionnaire.cages.index',
        'gestionnaire.alimentation.index',
        'gestionnaire.sante.index',
        'gestionnaire.ventes.index',
        'gestionnaire.ventes.create',
        'gestionnaire.depenses.index',
        'gestionnaire.plus',
        'gestionnaire.synchronisation.index',
    ],

    // Une saisie plus ancienne que ce nombre de jours est refusée à la synchronisation
    'age_max_jours' => 14,
],

];


