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
            PDO::ATTR_EMULATE_PREPARES   => true,
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

            // Migration : colonnes deleted_at pour le soft-delete (agriculteurs et cartes_rfid)
            $checkFarmerDel = $this->pdo->query("SHOW COLUMNS FROM `agriculteurs` LIKE 'deleted_at'")->fetch();
            if (!$checkFarmerDel) {
                $this->pdo->exec("ALTER TABLE `agriculteurs`
                    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
                    AFTER `statut`");
            }

            $checkCardDel = $this->pdo->query("SHOW COLUMNS FROM `cartes_rfid` LIKE 'deleted_at'")->fetch();
            if (!$checkCardDel) {
                $this->pdo->exec("ALTER TABLE `cartes_rfid`
                    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
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

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `commandes_bornes` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `borne_id`      INT NOT NULL,
                `type_commande` VARCHAR(50) NOT NULL
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

            // Migration MQTT : Tables en anglais demandées
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `stations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `location` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) DEFAULT 'offline',
                `api_key` VARCHAR(64) NULL,
                `last_ping` TIMESTAMP NULL DEFAULT NULL,
                `valve_status` ENUM('open', 'closed') DEFAULT 'closed',
                `flow_rate` FLOAT DEFAULT 0.0,
                `volume_distributed` FLOAT DEFAULT 0.0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `rfid_cards` (
                `uid` VARCHAR(50) PRIMARY KEY,
                `farmer_id` INT NOT NULL,
                `balance` DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
                `status` VARCHAR(20) DEFAULT 'active',
                `pin_code` VARCHAR(255) NULL,
                `associated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `last_used_at` TIMESTAMP NULL DEFAULT NULL,
                `deleted_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `water_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `card_uid` VARCHAR(50) NOT NULL,
                `station_id` INT NOT NULL,
                `volume_requested` DECIMAL(10, 2) NOT NULL,
                `volume_distributed` DECIMAL(10, 2) NOT NULL,
                `amount_debited` DECIMAL(10, 3) NOT NULL,
                `status` VARCHAR(20) DEFAULT 'success',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `mqtt_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `topic` VARCHAR(255) NOT NULL,
                `payload` TEXT NOT NULL,
                `direction` ENUM('in', 'out') NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `commands` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `station_id` INT NOT NULL,
                `command_type` VARCHAR(50) NOT NULL,
                `payload` TEXT NULL,
                `status` ENUM('pending', 'sent', 'ack', 'failed') NOT NULL DEFAULT 'pending',
                `created_by` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `sent_at` TIMESTAMP NULL DEFAULT NULL,
                `ack_at` TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `device_status` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `station_id` INT NOT NULL,
                `connection_status` VARCHAR(20) NOT NULL DEFAULT 'disconnected',
                `rssi` INT DEFAULT NULL,
                `valve_status` ENUM('open', 'closed') DEFAULT 'closed',
                `pump_status` ENUM('ON', 'OFF') DEFAULT 'OFF',
                `flow_rate` FLOAT DEFAULT 0.0,
                `volume_distributed` FLOAT DEFAULT 0.0,
                `uptime` BIGINT DEFAULT 0,
                `error_message` VARCHAR(255) NULL,
                `last_heartbeat` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `pump_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `station_id` INT NOT NULL,
                `action` ENUM('ON', 'OFF') NOT NULL,
                `volume` FLOAT DEFAULT 0.0,
                `duration` INT DEFAULT 0,
                `triggered_by` VARCHAR(50) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `alerts` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `station_id` INT NOT NULL,
                `alert_type` VARCHAR(50) NOT NULL,
                `description` TEXT NOT NULL,
                `resolved` TINYINT(1) DEFAULT 0,
                `resolved_at` TIMESTAMP NULL DEFAULT NULL,
                `source` VARCHAR(20) DEFAULT 'mqtt',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Migration : alignement des collations de cartes_rfid.uid et rfid_cards.uid pour éviter l'erreur 1267 dans les triggers
            $colInfo = $this->pdo->query("
                SELECT COLLATION_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cartes_rfid' AND COLUMN_NAME = 'uid'
            ")->fetch();

            if ($colInfo && !empty($colInfo['COLLATION_NAME'])) {
                $collation = $colInfo['COLLATION_NAME'];
                $this->pdo->exec("ALTER TABLE `rfid_cards` MODIFY `uid` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE {$collation}");
                $this->pdo->exec("ALTER TABLE `water_transactions` MODIFY `card_uid` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE {$collation}");
            }

            // Triggers de synchronisation automatique vers les tables en anglais
            try {
                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_bornes_insert`;");
                $this->pdo->exec("CREATE TRIGGER `trg_bornes_insert` AFTER INSERT ON `bornes`
                FOR EACH ROW
                BEGIN
                    INSERT INTO `stations` (id, name, location, status, last_ping, valve_status, flow_rate)
                    VALUES (
                        NEW.id, 
                        NEW.nom, 
                        NEW.localisation, 
                        IF(NEW.statut_connexion = 'en ligne', 'online', 'offline'), 
                        NEW.dernier_ping, 
                        IF(NEW.etat_vanne = 'ouverte', 'open', 'closed'), 
                        NEW.debit_instantane
                    )
                    ON DUPLICATE KEY UPDATE
                        name = NEW.nom,
                        location = NEW.localisation,
                        status = IF(NEW.statut_connexion = 'en ligne', 'online', 'offline'),
                        last_ping = NEW.dernier_ping,
                        valve_status = IF(NEW.etat_vanne = 'ouverte', 'open', 'closed'),
                        flow_rate = NEW.debit_instantane;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_bornes_update`;");
                $this->pdo->exec("CREATE TRIGGER `trg_bornes_update` AFTER UPDATE ON `bornes`
                FOR EACH ROW
                BEGIN
                    UPDATE `stations` SET
                        name = NEW.nom,
                        location = NEW.localisation,
                        status = IF(NEW.statut_connexion = 'en ligne', 'online', 'offline'),
                        last_ping = NEW.dernier_ping,
                        valve_status = IF(NEW.etat_vanne = 'ouverte', 'open', 'closed'),
                        flow_rate = NEW.debit_instantane
                    WHERE id = NEW.id;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_bornes_delete`;");
                $this->pdo->exec("CREATE TRIGGER `trg_bornes_delete` AFTER DELETE ON `bornes`
                FOR EACH ROW
                BEGIN
                    DELETE FROM `stations` WHERE id = OLD.id;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_cartes_rfid_insert`;");
                $this->pdo->exec("CREATE TRIGGER `trg_cartes_rfid_insert` AFTER INSERT ON `cartes_rfid`
                FOR EACH ROW
                BEGIN
                    INSERT INTO `rfid_cards` (uid, farmer_id, balance, status, pin_code, deleted_at)
                    VALUES (
                        NEW.uid, 
                        NEW.agriculteur_id, 
                        NEW.solde, 
                        IF(NEW.statut = 'active', 'active', 'suspended'), 
                        NEW.code_pin, 
                        NEW.deleted_at
                    )
                    ON DUPLICATE KEY UPDATE
                        farmer_id = NEW.agriculteur_id,
                        balance = NEW.solde,
                        status = IF(NEW.statut = 'active', 'active', 'suspended'),
                        pin_code = NEW.code_pin,
                        deleted_at = NEW.deleted_at;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_cartes_rfid_update`;");
                $this->pdo->exec("CREATE TRIGGER `trg_cartes_rfid_update` AFTER UPDATE ON `cartes_rfid`
                FOR EACH ROW
                BEGIN
                    UPDATE `rfid_cards` SET
                        farmer_id = NEW.agriculteur_id,
                        balance = NEW.solde,
                        status = IF(NEW.statut = 'active', 'active', 'suspended'),
                        pin_code = NEW.code_pin,
                        deleted_at = NEW.deleted_at,
                        last_used_at = NEW.derniere_utilisation
                    WHERE uid = NEW.uid;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_cartes_rfid_delete`;");
                $this->pdo->exec("CREATE TRIGGER `trg_cartes_rfid_delete` AFTER DELETE ON `cartes_rfid`
                FOR EACH ROW
                BEGIN
                    DELETE FROM `rfid_cards` WHERE uid = OLD.uid;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_transactions_insert`;");
                $this->pdo->exec("CREATE TRIGGER `trg_transactions_insert` AFTER INSERT ON `transactions`
                FOR EACH ROW
                BEGIN
                    INSERT INTO `water_transactions` (id, card_uid, station_id, volume_requested, volume_distributed, amount_debited, status, created_at)
                    VALUES (
                        NEW.id, 
                        NEW.carte_rfid_uid, 
                        NEW.borne_id, 
                        NEW.volume_demande, 
                        NEW.volume_distribue, 
                        NEW.montant_debite, 
                        IF(NEW.statut = 'reussie', 'success', 'failed'), 
                        NEW.date_heure
                    )
                    ON DUPLICATE KEY UPDATE
                        card_uid = NEW.carte_rfid_uid,
                        station_id = NEW.borne_id,
                        volume_requested = NEW.volume_demande,
                        volume_distributed = NEW.volume_distribue,
                        amount_debited = NEW.montant_debite,
                        status = IF(NEW.statut = 'reussie', 'success', 'failed'),
                        created_at = NEW.date_heure;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_alertes_insert`;");
                $this->pdo->exec("CREATE TRIGGER `trg_alertes_insert` AFTER INSERT ON `alertes`
                FOR EACH ROW
                BEGIN
                    INSERT INTO `alerts` (id, station_id, alert_type, description, resolved, resolved_at, source, created_at)
                    VALUES (NEW.id, NEW.borne_id, NEW.type_alerte, NEW.description, NEW.resolu, NEW.date_resolution, NEW.source, NEW.date_heure)
                    ON DUPLICATE KEY UPDATE
                        station_id = NEW.borne_id,
                        alert_type = NEW.type_alerte,
                        description = NEW.description,
                        resolved = NEW.resolu,
                        resolved_at = NEW.date_resolution,
                        source = NEW.source,
                        created_at = NEW.date_heure;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_alertes_update`;");
                $this->pdo->exec("CREATE TRIGGER `trg_alertes_update` AFTER UPDATE ON `alertes`
                FOR EACH ROW
                BEGIN
                    UPDATE `alerts` SET
                        resolved = NEW.resolu,
                        resolved_at = NEW.date_resolution,
                        description = NEW.description
                    WHERE id = NEW.id;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_commandes_bornes_insert`;");
                $this->pdo->exec("CREATE TRIGGER `trg_commandes_bornes_insert` AFTER INSERT ON `commandes_bornes`
                FOR EACH ROW
                BEGIN
                    INSERT INTO `commands` (id, station_id, command_type, status, created_by, created_at, sent_at, ack_at)
                    VALUES (
                        NEW.id, 
                        NEW.borne_id, 
                        NEW.type_commande, 
                        CASE 
                            WHEN NEW.statut = 'en_attente' THEN 'pending' 
                            WHEN NEW.statut = 'envoyee' THEN 'sent' 
                            WHEN NEW.statut = 'ack' THEN 'ack' 
                            ELSE 'failed' 
                        END, 
                        NEW.demande_par, 
                        NEW.date_creation, 
                        NEW.date_envoi, 
                        NEW.date_ack
                    )
                    ON DUPLICATE KEY UPDATE
                        station_id = NEW.borne_id,
                        command_type = NEW.type_commande,
                        status = CASE 
                            WHEN NEW.statut = 'en_attente' THEN 'pending' 
                            WHEN NEW.statut = 'envoyee' THEN 'sent' 
                            WHEN NEW.statut = 'ack' THEN 'ack' 
                            ELSE 'failed' 
                        END,
                        created_by = NEW.demande_par,
                        created_at = NEW.date_creation,
                        sent_at = NEW.date_envoi,
                        ack_at = NEW.date_ack;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_commandes_bornes_update`;");
                $this->pdo->exec("CREATE TRIGGER `trg_commandes_bornes_update` AFTER UPDATE ON `commandes_bornes`
                FOR EACH ROW
                BEGIN
                    UPDATE `commands` SET
                        status = CASE 
                            WHEN NEW.statut = 'en_attente' THEN 'pending' 
                            WHEN NEW.statut = 'envoyee' THEN 'sent' 
                            WHEN NEW.statut = 'ack' THEN 'ack' 
                            ELSE 'failed' 
                        END,
                        sent_at = NEW.date_envoi,
                        ack_at = NEW.date_ack
                    WHERE id = NEW.id;
                END;");

                $this->pdo->exec("DROP TRIGGER IF EXISTS `trg_commandes_bornes_delete`;");
                $this->pdo->exec("CREATE TRIGGER `trg_commandes_bornes_delete` AFTER DELETE ON `commandes_bornes`
                FOR EACH ROW
                BEGIN
                    DELETE FROM `commands` WHERE id = OLD.id;
                END;");
            } catch (\Throwable $e) {
                // Supprimer les exceptions de création simultanée de triggers
            }

            // Auto-synchronisation initiale si les tables anglaises sont vides
            $countStations = $this->pdo->query("SELECT COUNT(*) FROM `stations`")->fetchColumn();
            if ($countStations == 0) {
                $this->pdo->exec("INSERT INTO `stations` (id, name, location, status, api_key, last_ping, valve_status, flow_rate)
                    SELECT 
                        id, 
                        nom, 
                        localisation, 
                        IF(statut_connexion = 'en ligne', 'online', 'offline'), 
                        api_key, 
                        dernier_ping, 
                        IF(etat_vanne = 'ouverte', 'open', 'closed'), 
                        debit_instantane 
                    FROM `bornes`
                    ON DUPLICATE KEY UPDATE id = VALUES(id)");
            }

            $countRfidCards = $this->pdo->query("SELECT COUNT(*) FROM `rfid_cards`")->fetchColumn();
            if ($countRfidCards == 0) {
                $this->pdo->exec("INSERT INTO `rfid_cards` (uid, farmer_id, balance, status, pin_code, deleted_at, associated_at, last_used_at)
                    SELECT 
                        uid, 
                        agriculteur_id, 
                        solde, 
                        IF(statut = 'active', 'active', 'suspended'), 
                        code_pin, 
                        deleted_at, 
                        date_association, 
                        derniere_utilisation 
                    FROM `cartes_rfid`
                    ON DUPLICATE KEY UPDATE uid = VALUES(uid)");
            }

            $countWaterTransactions = $this->pdo->query("SELECT COUNT(*) FROM `water_transactions`")->fetchColumn();
            if ($countWaterTransactions == 0) {
                $this->pdo->exec("INSERT INTO `water_transactions` (id, card_uid, station_id, volume_requested, volume_distributed, amount_debited, status, created_at)
                    SELECT 
                        id, 
                        carte_rfid_uid, 
                        borne_id, 
                        volume_demande, 
                        volume_distribue, 
                        montant_debite, 
                        IF(statut = 'reussie', 'success', 'failed'), 
                        date_heure 
                    FROM `transactions`
                    ON DUPLICATE KEY UPDATE id = VALUES(id)");
            }

            $countAlerts = $this->pdo->query("SELECT COUNT(*) FROM `alerts`")->fetchColumn();
            if ($countAlerts == 0) {
                $this->pdo->exec("INSERT INTO `alerts` (id, station_id, alert_type, description, resolved, resolved_at, source, created_at)
                    SELECT 
                        id, 
                        borne_id, 
                        type_alerte, 
                        description, 
                        resolu, 
                        date_resolution, 
                        source, 
                        date_heure 
                    FROM `alertes`
                    ON DUPLICATE KEY UPDATE id = VALUES(id)");
            }

            $countCommands = $this->pdo->query("SELECT COUNT(*) FROM `commands`")->fetchColumn();
            if ($countCommands == 0) {
                $this->pdo->exec("INSERT INTO `commands` (id, station_id, command_type, status, created_by, created_at, sent_at, ack_at)
                    SELECT 
                        id, 
                        borne_id, 
                        type_commande, 
                        CASE 
                            WHEN statut = 'en_attente' THEN 'pending' 
                            WHEN statut = 'envoyee' THEN 'sent' 
                            WHEN statut = 'ack' THEN 'ack' 
                            ELSE 'failed' 
                        END, 
                        demande_par, 
                        date_creation, 
                        date_envoi, 
                        date_ack 
                    FROM `commandes_bornes`
                    ON DUPLICATE KEY UPDATE id = VALUES(id)");
            }


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
