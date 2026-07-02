<?php
/**
 * Page de diagnostic rapide - SUPPRIMER EN PRODUCTION
 * Acceder via : http://localhost/water/public/debug.php
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC APPLICATION H2O RFID ===\n\n";

// 1. Chemins PHP
echo "1. Chemins fichiers :\n";
echo "   __DIR__        = " . __DIR__ . "\n";
echo "   Racine projet  = " . dirname(__DIR__) . "\n";
$configPath = dirname(__DIR__) . '/config/config.php';
echo "   Config path    = " . $configPath . "\n";
echo "   Config existe  = " . (file_exists($configPath) ? 'OUI' : 'NON !!!') . "\n\n";

// 2. Autoloader
spl_autoload_register(function ($class) {
    $base_dir = dirname(__DIR__) . '/app/';
    $prefix   = 'App\\';
    $len      = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base_dir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Models\Admin;

// 3. Connexion DB
echo "2. Connexion MariaDB :\n";
try {
    $pdo = Database::getInstance()->getConnection();
    echo "   Connexion PDO  = OK\n\n";
} catch (Exception $e) {
    echo "   ERREUR PDO : " . $e->getMessage() . "\n\n";
    exit;
}

// 4. Verification hash admin
echo "3. Verification admin (admin@water.local) :\n";
$row = $pdo->query("SELECT email, password, statut FROM administrateurs WHERE email = 'admin@water.local'")->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "   ERREUR : Aucun admin trouve !\n";
} else {
    echo "   Email  = " . $row['email'] . "\n";
    echo "   Statut = " . $row['statut'] . "\n";
    echo "   Hash   = " . $row['password'] . "\n";
    $valid = password_verify('admin123', $row['password']);
    echo "   password_verify('admin123') = " . ($valid ? 'OUI - OK !' : 'NON - Hash invalide !') . "\n\n";

    if (!$valid) {
        echo "Correction automatique du hash...\n";
        $newHash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE administrateurs SET password = ? WHERE email = 'admin@water.local'");
        $stmt->execute([$newHash]);
        echo "Hash mis a jour. Reessayez la connexion.\n";
    } else {
        echo "Le hash est valide. La connexion doit fonctionner.\n";
        echo "Testez avec : admin@water.local / admin123\n";
    }
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
echo "Supprimez ce fichier (public/debug.php) apres utilisation !\n";
