<?php
/**
 * Point d'entrée principal de l'application (Front Controller)
 * Projet : Distribution d'Eau Automatisée par Carte RFID Prépayée
 */

// 1. Définir le mécanisme d'Autoloading pour l'espace de noms App\
spl_autoload_register(function ($class) {
    // Espace de noms racine
    $prefix = 'App\\';
    
    // Répertoire de base correspondant à cet espace de noms
    $base_dir = dirname(__DIR__) . '/app/';

    // Vérifier si la classe utilise ce préfixe
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Récupérer le nom relatif de la classe
    $relative_class = substr($class, $len);

    // Remplacer les séparateurs de l'espace de noms par des séparateurs de dossiers,
    // puis ajouter l'extension '.php'
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si le fichier existe, le charger
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Session;
use App\Core\Router;

// 2. Démarrer le gestionnaire de session
Session::start();

// 3. Initialiser le routeur et traiter la requête HTTP
$router = new Router();
$router->dispatch();
