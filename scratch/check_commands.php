<?php
require 'public/index.php';
$pdo = \App\Core\Database::getInstance()->getConnection();
echo "Recent commands in commandes_bornes:\n";
$cmds = $pdo->query("SELECT * FROM commandes_bornes ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cmds as $c) {
    echo "ID: {$c['id']} | Borne ID: {$c['borne_id']} | Type: {$c['type_commande']} | Statut: {$c['statut']} | Created: {$c['date_creation']} | Sent: {$c['date_envoi']}\n";
}
