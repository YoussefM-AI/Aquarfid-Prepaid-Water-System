<?php
/**
 * Global Application Configuration — EXAMPLE FILE
 * ---------------------------------------------------
 * Copy this file to config/config.php and fill in your values.
 * NEVER commit config/config.php to version control.
 */

return [
    // MariaDB / MySQL connection settings
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'dbname'   => 'water_db',       // Your database name
        'username' => 'root',           // DB username
        'password' => '',               // DB password
        'charset'  => 'utf8mb4'
    ],

    // General application settings
    'app' => [
        'name' => 'Système RFID Eau',
        'env'  => 'development',        // 'development' or 'production'
        'url'  => 'http://water.test'   // Your local/virtual domain
    ]
];
