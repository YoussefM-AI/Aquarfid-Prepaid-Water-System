<?php
require 'public/index.php';
$pdo = \App\Core\Database::getInstance()->getConnection();
$admin = $pdo->query("SELECT password FROM administrateurs WHERE email = 'admin@water.local'")->fetchColumn();
echo "Stored Hash: " . $admin . "\n";
echo "Verifying with 'admin123': " . (password_verify('admin123', $admin) ? 'Valid!' : 'Invalid!') . "\n";
