<?php
require 'public/index.php';
$pdo = \App\Core\Database::getInstance()->getConnection();
$newHash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE administrateurs SET password = :pass WHERE email = 'admin@water.local'");
$ok = $stmt->execute(['pass' => $newHash]);
if ($ok) {
    echo "Successfully updated admin@water.local password to 'admin123'.\n";
} else {
    echo "Failed to update password.\n";
}
