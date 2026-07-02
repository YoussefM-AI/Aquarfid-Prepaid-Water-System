<?php
/**
 * Fichier de configuration globale de l'application
 * Projet : Distribution d'Eau Automatisée par Carte RFID Prépayée
 */

return [
    // Configuration de la base de données MariaDB (Laragon par défaut)
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'dbname'   => 'water_db',
        'username' => 'root',
        'password' => '', // Pas de mot de passe par défaut sous Laragon
        'charset'  => 'utf8mb4'
    ],

    // Paramètres généraux de l'application
    'app' => [
        'name' => 'Système RFID Eau',
        'env'  => 'development', // development, production
        'url'  => 'http://water.test' // Domaine virtuel par défaut de Laragon
    ]
];
