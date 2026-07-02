<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("❌ Accès refusé. Ce script ne peut être exécuté qu'en ligne de commande (CLI).\n");
}

/**
 * Script d'initialisation automatique de la base de données
 * Projet : Distribution d'Eau Automatisée par Carte RFID Prépayée
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Initialisation de la Base de Données MariaDB ===\n\n";

$configPath = __DIR__ . '/config/config.php';
if (!file_exists($configPath)) {
    die("[ERREUR] Le fichier de configuration n'existe pas.\n");
}

$config = require $configPath;
$dbConfig = $config['db'];

// 1. Connexion au serveur MariaDB (sans sélectionner de base de données d'abord)
try {
    $dsn = sprintf("mysql:host=%s;port=%s;charset=%s", $dbConfig['host'], $dbConfig['port'], $dbConfig['charset']);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    echo "1. Connexion au serveur MariaDB...\n";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
    echo "   [SUCCÈS] Connexion établie.\n\n";
    
} catch (PDOException $e) {
    die("[ERREUR] Impossible de se connecter au serveur MariaDB : " . $e->getMessage() . "\n" .
        "Assurez-vous que Laragon est démarré et que MySQL est actif.\n");
}

// 2. Lecture du fichier database.sql
$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("[ERREUR] Le fichier SQL d'initialisation 'database.sql' est introuvable.\n");
}

echo "2. Lecture du fichier 'database.sql'...\n";
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    die("[ERREUR] Impossible de lire le fichier 'database.sql'.\n");
}
echo "   [SUCCÈS] Fichier lu.\n\n";

// 3. Exécution des requêtes SQL
echo "3. Exécution du script SQL (création DB, tables, seeds)...\n";
try {
    $pdo->exec($sql);
    echo "   [SUCCÈS] Toutes les requêtes ont été exécutées avec succès !\n\n";
    echo "La base de données 'water_db' a été initialisée avec des données de test.\n";
} catch (PDOException $e) {
    echo "   [ERREUR] Erreur lors de l'exécution du script SQL :\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nEssai d'exécution bloc par bloc...\n";
    
    try {
        $cleanSql = preg_replace('/--.*\n/', '', $sql);
        $queries = explode(';', $cleanSql);
        
        $pdo->beginTransaction();
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }
        $pdo->commit();
        echo "   [SUCCÈS] Exécution bloc par bloc réussie.\n";
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("[ERREUR FATALE] Échec de l'exécution alternative : " . $ex->getMessage() . "\n");
    }
}

echo "\n=== Initialisation Terminée ===\n";
echo "Vous pouvez maintenant lancer le script 'test_db_connection.php' pour vérifier.\n";
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=water_db;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    echo "=== Diagnostic Admin ===\n\n";

    // Lire le hash actuel
    $row = $pdo->query("SELECT email, password, statut FROM administrateurs WHERE email = 'admin@water.local'")->fetch();

    if (!$row) {
        echo "ERREUR : Aucun admin trouve pour admin@water.local\n";
        exit;
    }

    echo "Email  : " . $row['email'] . "\n";
    echo "Statut : " . $row['statut'] . "\n";
    echo "Hash   : " . $row['password'] . "\n";
    echo "password_verify('admin123') = " . (password_verify('admin123', $row['password']) ? 'OUI' : 'NON') . "\n\n";

    // Generer un hash frais et corriger
    $newHash = password_hash('admin123', PASSWORD_BCRYPT);
    echo "Nouveau hash : " . $newHash . "\n\n";

    $stmt = $pdo->prepare("UPDATE administrateurs SET password = :pw WHERE email = 'admin@water.local'");
    $stmt->execute(['pw' => $newHash]);

    echo "Mise a jour : " . $stmt->rowCount() . " ligne(s) affectee(s)\n";

    // Re-verifier
    $row2 = $pdo->query("SELECT password FROM administrateurs WHERE email = 'admin@water.local'")->fetch();
    echo "Verification finale = " . (password_verify('admin123', $row2['password']) ? 'OK - Connexion devrait fonctionner' : 'ECHEC') . "\n";

} catch (Exception $e) {
    echo "ERREUR PDO : " . $e->getMessage() . "\n";
}
