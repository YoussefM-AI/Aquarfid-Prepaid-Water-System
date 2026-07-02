<?php
/**
 * Script de test de connexion à la base de données
 * Projet : Distribution d'Eau Automatisée par Carte RFID Prépayée
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Démarrage du Test de Connexion MariaDB ===\n\n";

// 1. Autoloader simple pour charger App\Core\Database
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;

try {
    echo "1. Tentative d'initialisation de l'instance Database...\n";
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "   [SUCCÈS] Connexion établie avec succès via PDO.\n\n";

    echo "2. Vérification des tables disponibles dans 'water_db'...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "   [WARNING] Aucune table trouvée. Avez-vous exécuté le script SQL 'database.sql' dans votre serveur MariaDB Laragon ?\n\n";
    } else {
        echo "   [SUCCÈS] Tables trouvées (" . count($tables) . ") :\n";
        foreach ($tables as $table) {
            echo "     - $table\n";
        }
        echo "\n";
    }

    echo "3. Récupération de la configuration globale (tarif de l'eau) ...\n";
    $stmt = $pdo->query("SELECT param_value FROM configurations WHERE param_key = 'price_per_litre'");
    $price = $stmt->fetchColumn();

    if ($price !== false) {
        echo "   [SUCCÈS] Prix de l'eau par litre configuré : " . $price . " DT / Litre (soit " . ($price * 1000) . " DT / m³)\n\n";
    } else {
        echo "   [WARNING] Paramètre 'price_per_litre' absent de la table 'configurations'.\n\n";
    }

    echo "4. Test de lecture des agriculteurs actifs...\n";
    $stmt = $pdo->query("SELECT nom, prenom, telephone, statut FROM agriculteurs");
    $farmers = $stmt->fetchAll();

    if (empty($farmers)) {
        echo "   [INFO] Aucun agriculteur dans la base de données.\n";
    } else {
        echo "   [SUCCÈS] Agriculteurs trouvés :\n";
        foreach ($farmers as $farmer) {
            echo "     - " . $farmer['prenom'] . " " . $farmer['nom'] . " (" . $farmer['statut'] . ") - Tel: " . $farmer['telephone'] . "\n";
        }
    }

} catch (Exception $e) {
    echo "   [ERREUR] Une erreur est survenue :\n";
    echo "   Message : " . $e->getMessage() . "\n";
    echo "   Fichier : " . $e->getFile() . " à la ligne " . $e->getLine() . "\n";
    echo "\nConsignes sous Laragon :\n";
    echo "1. Assurez-vous que Laragon est lancé et que le service MySQL/MariaDB est actif (bouton 'Démarrer Tout').\n";
    echo "2. Assurez-vous d'avoir créé la base de données 'water_db' et d'y avoir injecté le fichier 'database.sql'.\n";
    echo "3. Vérifiez les informations d'identification dans le fichier 'config/config.php'.\n";
}

echo "\n=== Fin du Test ===\n";
