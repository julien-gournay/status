<?php
return [
    'sites' => [
        'web' => [
            'https://juliengournay.fr',
            'https://dev.juliengournay.fr',

            'https://cours.juliengournay.fr',
            'https://filmotech.juliengournay.fr',
            'https://marieteam.juliengournay.fr',
            'https://filmotech.juliengournay.fr',
            'https://legrandberger.donovanmercier.fr/',

            'https://password.juliengournay.fr',
            'https://evenement.juliengournay.fr',
            'https://simulateur-bts-sio.juliengournay.fr',
        ],
        'redirect' => [
            'https://nas.juliengournay.fr',
            'https://linkedin.juliengournay.fr/',
            'https://documentation.juliengournay.fr/',
            'https://ressources.juliengournay.fr/',
        ]
    ],
    'refresh_interval' => 180000, // 3 minutes en millisecondes
    'history_limit' => 100, // Nombre maximum d'entrées dans l'historique
    'timeout' => 10, // Timeout en secondes pour les requêtes
]; 