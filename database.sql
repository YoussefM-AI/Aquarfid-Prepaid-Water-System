-- Script SQL de création et d'initialisation de la base de données
-- Projet : Distribution d'Eau Automatisée par Carte RFID Prépayée

CREATE DATABASE IF NOT EXISTS `water_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `water_db`;

-- 1. Table des configurations globales du système
CREATE TABLE IF NOT EXISTS `configurations` (
    `param_key` VARCHAR(50) PRIMARY KEY,
    `param_value` VARCHAR(255) NOT NULL,
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `configurations` (`param_key`, `param_value`, `description`) 
VALUES ('price_per_litre', '0.200', 'Prix de l''eau en Dinars Tunisiens par Litre')
ON DUPLICATE KEY UPDATE `param_value` = '0.200';

-- 2. Table des administrateurs (pour l'application mobile et supervision)
CREATE TABLE IF NOT EXISTS `administrateurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `statut` VARCHAR(20) DEFAULT 'actif',
    `role` VARCHAR(50) DEFAULT 'secretaire',
    `perm_view_clients` TINYINT(1) DEFAULT 1,
    `perm_view_balances` TINYINT(1) DEFAULT 1,
    `perm_view_transactions` TINYINT(1) DEFAULT 1,
    `perm_manage_clients` TINYINT(1) DEFAULT 0,
    `perm_can_recharge` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `administrateurs` (`nom`, `prenom`, `email`, `password`, `statut`, `role`, `perm_view_clients`, `perm_view_balances`, `perm_view_transactions`, `perm_manage_clients`, `perm_can_recharge`)
VALUES ('Dupont', 'Jean', 'admin@water.local', '$2y$10$tAOpeukLHLCPZWDA.vbKp.wb0c8Vx5HnB6QkTBDovYAJQE6OdMJUC', 'actif', 'admin', 1, 1, 1, 1, 1)
ON DUPLICATE KEY UPDATE `password` = '$2y$10$tAOpeukLHLCPZWDA.vbKp.wb0c8Vx5HnB6QkTBDovYAJQE6OdMJUC', `statut` = 'actif';

-- 3. Table des agriculteurs (AJOUT DU SOFT DELETE ICI)
CREATE TABLE IF NOT EXISTS `agriculteurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `telephone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(150) UNIQUE NULL,
    `statut` VARCHAR(20) DEFAULT 'actif',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Table des cartes RFID (AJOUT DU SOFT DELETE ET RESTRICT ICI)
CREATE TABLE IF NOT EXISTS `cartes_rfid` (
    `uid` VARCHAR(50) PRIMARY KEY,
    `agriculteur_id` INT NOT NULL,
    `solde` DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    `statut` VARCHAR(20) DEFAULT 'active',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `date_association` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `derniere_utilisation` TIMESTAMP NULL,
    FOREIGN KEY (`agriculteur_id`) REFERENCES `agriculteurs`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Table des bornes interactives de distribution (AJOUT DE LA CLÉ API ICI)
CREATE TABLE IF NOT EXISTS `bornes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `localisation` VARCHAR(255) NOT NULL,
    `statut_connexion` VARCHAR(20) DEFAULT 'hors ligne',
    `api_key` VARCHAR(64) NOT NULL UNIQUE,
    `dernier_ping` TIMESTAMP NULL
) ENGINE=InnoDB;

-- 6. Table des transactions (historique de distribution d'eau)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `carte_rfid_uid` VARCHAR(50) NOT NULL,
    `borne_id` INT NOT NULL,
    `volume_demande` DECIMAL(10, 2) NOT NULL,
    `volume_distribue` DECIMAL(10, 2) NOT NULL,
    `montant_debite` DECIMAL(10, 3) NOT NULL,
    `date_heure` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `statut` VARCHAR(20) DEFAULT 'reussie',
    FOREIGN KEY (`carte_rfid_uid`) REFERENCES `cartes_rfid`(`uid`) ON DELETE RESTRICT,
    FOREIGN KEY (`borne_id`) REFERENCES `bornes`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. Table des alertes de sécurité (fuites, trop-plein, etc.)
CREATE TABLE IF NOT EXISTS `alertes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `borne_id` INT NOT NULL,
    `type_alerte` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `date_heure` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolu` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`borne_id`) REFERENCES `bornes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- JEU DE DONNÉES DE DÉMONSTRATION (SEEDERS)
-- ==========================================

INSERT INTO `agriculteurs` (`id`, `nom`, `prenom`, `telephone`, `email`, `statut`) VALUES
(1, 'Ben Ali', 'Mohamed', '+21698765432', 'mohamed.benali@email.com', 'actif'),
(2, 'Trabelsi', 'Amira', '+21655123456', 'amira.trabelsi@email.com', 'actif'),
(3, 'Gharbi', 'Salem', '+21622334455', 'salem.gharbi@email.com', 'suspendu')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `cartes_rfid` (`uid`, `agriculteur_id`, `solde`, `statut`) VALUES
('04A32B125C6D80', 1, 150.000, 'active'),
('04B76C982A1E40', 2, 25.500, 'active'),
('04F5E8D21B7C90', 3, 0.000, 'suspendue')
ON DUPLICATE KEY UPDATE `uid` = `uid`;

INSERT INTO `bornes` (`id`, `nom`, `localisation`, `statut_connexion`, `api_key`, `dernier_ping`) VALUES
(1, 'Borne Secteur Nord (Cuve principale)', 'Zone Agricole Nord - Secteur A', 'en ligne', 'borne1_secret_9a8b7c6d', NOW()),
(2, 'Borne Secteur Sud', 'Plaine du Sud - Secteur C', 'hors ligne', 'borne2_secret_1e2f3g4h', DATE_SUB(NOW(), INTERVAL 2 HOUR))
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `transactions` (`carte_rfid_uid`, `borne_id`, `volume_demande`, `volume_distribue`, `montant_debite`, `date_heure`, `statut`) VALUES
('04A32B125C6D80', 1, 50.00, 50.00, 10.000, DATE_SUB(NOW(), INTERVAL 1 DAY), 'reussie'),
('04A32B125C6D80', 1, 100.00, 100.00, 20.000, DATE_SUB(NOW(), INTERVAL 12 HOUR), 'reussie'),
('04B76C982A1E40', 1, 20.00, 18.50, 3.700, DATE_SUB(NOW(), INTERVAL 6 HOUR), 'interrompue_erreur')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `alertes` (`borne_id`, `type_alerte`, `description`, `date_heure`, `resolu`) VALUES
(1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', DATE_SUB(NOW(), INTERVAL 6 HOUR), 1)
ON DUPLICATE KEY UPDATE `id` = `id`;