<?php

namespace App\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Classe Database (Pattern Singleton)
 * Gère la connexion unique à la base de données MariaDB via PDO.
 */
class Database {
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Constructeur privé pour empêcher l'instanciation directe.
     */
    private function __construct() {
        // Déterminer le chemin absolu du fichier de configuration
        $configPath = dirname(__DIR__, 2) . '/config/config.php';
        
        if (!file_exists($configPath)) {
            throw new Exception("Le fichier de configuration est introuvable à l'adresse : " . $configPath);
        }

        $config = require $configPath;
        $dbConfig = $config['db'];

        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['dbname'],
            $dbConfig['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);

            // Migration automatique (Self-healing Schema)
            $check = $this->pdo->query("SHOW COLUMNS FROM `administrateurs` LIKE 'role'")->fetch();
            if (!$check) {
                $this->pdo->exec("ALTER TABLE `administrateurs` 
                    ADD COLUMN `role` VARCHAR(50) DEFAULT 'secretaire',
                    ADD COLUMN `perm_view_clients` TINYINT(1) DEFAULT 1,
                    ADD COLUMN `perm_view_balances` TINYINT(1) DEFAULT 1,
                    ADD COLUMN `perm_view_transactions` TINYINT(1) DEFAULT 1,
                    ADD COLUMN `perm_manage_clients` TINYINT(1) DEFAULT 0,
                    ADD COLUMN `perm_can_recharge` TINYINT(1) DEFAULT 0");
                
                // Mettre à jour l'admin existant avec toutes les permissions
                $this->pdo->exec("UPDATE `administrateurs` SET 
                    `role` = 'admin',
                    `perm_view_clients` = 1,
                    `perm_view_balances` = 1,
                    `perm_view_transactions` = 1,
                    `perm_manage_clients` = 1,
                    `perm_can_recharge` = 1");
            }

            // Migration : colonne code_pin pour l'auth PIN du kiosk
            $checkPin = $this->pdo->query("SHOW COLUMNS FROM `cartes_rfid` LIKE 'code_pin'")->fetch();
            if (!$checkPin) {
                $this->pdo->exec("ALTER TABLE `cartes_rfid`
                    ADD COLUMN `code_pin` VARCHAR(255) NULL DEFAULT NULL
                    COMMENT 'Code PIN 4 chiffres hashé bcrypt'
                    AFTER `statut`");
            }

            // Migration : colonne date_resolution dans alertes (pour suivre quand une alerte a été résolue)
            $checkRes = $this->pdo->query("SHOW COLUMNS FROM `alertes` LIKE 'date_resolution'")->fetch();
            if (!$checkRes) {
                $this->pdo->exec("ALTER TABLE `alertes`
                    ADD COLUMN `date_resolution` TIMESTAMP NULL DEFAULT NULL
                    COMMENT 'Date de résolution de l\\'alerte'
                    AFTER `resolu`");
            }

            // Migration MQTT : colonne source dans alertes (http ou mqtt)
            $checkAlerteSrc = $this->pdo->query("SHOW COLUMNS FROM `alertes` LIKE 'source'")->fetch();
            if (!$checkAlerteSrc) {
                $this->pdo->exec("ALTER TABLE `alertes`
                    ADD COLUMN `source` ENUM('http','mqtt') NOT NULL DEFAULT 'http'
                    COMMENT 'Canal de réception de l\\'alerte (http REST ou mqtt)'
                    AFTER `date_resolution`");
            }

            // Migration MQTT : colonnes état temps réel sur bornes
            $checkEtatVanne = $this->pdo->query("SHOW COLUMNS FROM `bornes` LIKE 'etat_vanne'")->fetch();
            if (!$checkEtatVanne) {
                $this->pdo->exec("ALTER TABLE `bornes`
                    ADD COLUMN `etat_vanne` ENUM('ouverte','fermee') NOT NULL DEFAULT 'fermee'
                        COMMENT 'Dernier état connu de la vanne (via MQTT)',
                    ADD COLUMN `debit_instantane` FLOAT NULL DEFAULT NULL
                        COMMENT 'Dernier débit instantané connu en L/min (via MQTT)',
                    ADD COLUMN `capteur_fuite` TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT '1 = fuite sol détectée (via MQTT)',
                    ADD COLUMN `capteur_trop_plein` TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT '1 = trop-plein détecté (via MQTT)',
                    ADD COLUMN `derniere_telemerie_mqtt` TIMESTAMP NULL DEFAULT NULL
                        COMMENT 'Timestamp de la dernière télémétrie MQTT reçue'");
            }

            // Migration MQTT : table commandes_bornes
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `commandes_bornes` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `borne_id`      INT NOT NULL,
                `type_commande` ENUM('start_pompe','stop_pompe') NOT NULL
                                COMMENT 'Type de commande envoyée à la borne',
                `statut`        ENUM('en_attente','envoyee','ack','echec') NOT NULL DEFAULT 'en_attente'
                                COMMENT 'Cycle de vie de la commande',
                `demande_par`   INT NULL DEFAULT NULL
                                COMMENT 'ID de l\\'administrateur ayant émis la commande',
                `date_creation` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `date_envoi`    TIMESTAMP NULL DEFAULT NULL
                                COMMENT 'Horodatage de la publication MQTT par le worker',
                `date_ack`      TIMESTAMP NULL DEFAULT NULL
                                COMMENT 'Horodatage de l\\'accusé de réception ESP32',
                PRIMARY KEY (`id`),
                KEY `idx_statut` (`statut`),
                KEY `idx_borne_id` (`borne_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Journal des commandes de pompe envoyées aux bornes via MQTT'");

        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données : " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Récupère l'instance unique de la classe Database.
     *
     * @return Database
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupère l'objet de connexion PDO actif.
     *
     * @return PDO
     */
    public function getConnection(): PDO {
        return $this->pdo;
    }

    /**
     * Empêcher le clonage de l'instance.
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation de l'instance.
     */
    public function __wakeup() {
        throw new Exception("Impossible de désérialiser un Singleton.");
    }
}
