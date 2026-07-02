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
/**
 * Script d'initialisation des PINs de démonstration.
 * La colonne code_pin est maintenant créée AUTOMATIQUEMENT par Database.php
 * Ce script sert uniquement à définir les PINs de test pour les cartes de démo.
 *
 * Ouvrir une seule fois : http://localhost/water/add_pin_migration.php
 * Supprimer ce fichier après utilisation.
 */

// Autoloader minimal
spl_autoload_register(function ($class) {
    $prefix   = 'App\\';
    $base_dir = __DIR__ . '/app/';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Session;
Session::start();

echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Migration PIN</title>
<style>
  body { font-family: monospace; background:#0f172a; color:#e2e8f0; padding:40px; }
  .ok  { color:#10B981; } .err { color:#EF4444; } .warn { color:#F59E0B; }
  pre  { background:#1e293b; padding:16px; border-radius:8px; }
  h2   { color:#38BDF8; }
  .box { background:#1e293b; border:1px solid #334155; border-radius:12px; padding:24px; max-width:600px; }
</style></head><body><div class="box">';

echo '<h2>🔑 Initialisation des PINs de démonstration</h2>';

try {
    $db = \App\Core\Database::getInstance()->getConnection();

    // Vérifier si la colonne existe (créée auto par Database.php)
    $stmt = $db->query("SHOW COLUMNS FROM `cartes_rfid` LIKE 'code_pin'");
    if ($stmt->rowCount() === 0) {
        echo '<p class="err">❌ La colonne code_pin n\'existe pas encore.<br>
              Rechargez la page — Database.php la crée automatiquement.</p>';
        exit;
    }
    echo '<p class="ok">✅ Colonne <code>code_pin</code> présente.</p>';

    // PINs de démo
    $demoPins = [
        '04A32B125C6D80' => '1234',  // Mohamed Ben Ali
        '04B76C982A1E40' => '5678',  // Amira Trabelsi
    ];

    $stmtUpd = $db->prepare("UPDATE `cartes_rfid` SET `code_pin` = :hash WHERE `uid` = :uid");

    foreach ($demoPins as $uid => $pin) {
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $ok   = $stmtUpd->execute(['hash' => $hash, 'uid' => $uid]);
        if ($ok && $stmtUpd->rowCount() > 0) {
            echo "<p class='ok'>✅ PIN <strong>{$pin}</strong> → carte <code>{$uid}</code></p>";
        } else {
            echo "<p class='warn'>⚠️ Carte <code>{$uid}</code> introuvable en base.</p>";
        }
    }

    echo '<hr style="border-color:#334155;margin:20px 0;">';
    echo '<h2>📋 PINs de test disponibles</h2>';
    echo '<pre>';
    echo "Mohamed Ben Ali  (04A32B125C6D80) → PIN : 1234\n";
    echo "Amira Trabelsi   (04B76C982A1E40) → PIN : 5678\n";
    echo '</pre>';

    echo '<p class="ok" style="font-weight:bold;">✅ Migration terminée !</p>';
    echo '<p>→ Allez sur <a href="http://localhost/water/public/kiosk.html" style="color:#38BDF8;">le kiosk</a> et testez le mode PIN.</p>';
    echo '<p class="err">⚠️ Supprimez ce fichier après utilisation !</p>';

} catch (Exception $e) {
    echo '<p class="err">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</div></body></html>';
