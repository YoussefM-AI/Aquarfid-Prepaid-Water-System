<?php
require 'public/index.php';
$pdo = \App\Core\Database::getInstance()->getConnection();
$admins = $pdo->query("SELECT id, nom, prenom, email, statut, role FROM administrateurs")->fetchAll(PDO::FETCH_ASSOC);
echo "List of Administrators in Database:\n";
foreach ($admins as $ad) {
    echo "ID: {$ad['id']} | Name: {$ad['prenom']} {$ad['nom']} | Email: {$ad['email']} | Statut: {$ad['statut']} | Role: {$ad['role']}\n";
}
