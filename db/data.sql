-- --------------------------------------------------------
-- Hôte:                         127.0.0.1
-- Version du serveur:           8.0.30 - MySQL Community Server - GPL
-- SE du serveur:                Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Listage de la structure de la base pour water_db
CREATE DATABASE IF NOT EXISTS `water_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `water_db`;

-- Listage de la structure de table water_db. administrateurs
CREATE TABLE IF NOT EXISTS `administrateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'secretaire',
  `perm_view_clients` tinyint(1) DEFAULT '1',
  `perm_view_balances` tinyint(1) DEFAULT '1',
  `perm_view_transactions` tinyint(1) DEFAULT '1',
  `perm_manage_clients` tinyint(1) DEFAULT '0',
  `perm_can_recharge` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.administrateurs : ~1 rows (environ)
INSERT INTO `administrateurs` (`id`, `nom`, `prenom`, `email`, `password`, `statut`, `created_at`, `role`, `perm_view_clients`, `perm_view_balances`, `perm_view_transactions`, `perm_manage_clients`, `perm_can_recharge`) VALUES
	(1, 'Dupont', 'Jean', 'admin@water.local', '$2y$10$tAOpeukLHLCPZWDA.vbKp.wb0c8Vx5HnB6QkTBDovYAJQE6OdMJUC', 'actif', '2026-06-09 14:00:57', 'admin', 1, 1, 1, 1, 1),
	(5, 'Secretaire2', 'Test', 'secretaire2@water.local', '$2y$10$8xsxCSla6872tRbscwh6h.KdP26iRuMU3jyB/1L4Yq9luU7mWf5SO', 'actif', '2026-06-23 09:52:18', 'secretaire', 1, 1, 0, 0, 0);

-- Listage de la structure de table water_db. agriculteurs
CREATE TABLE IF NOT EXISTS `agriculteurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'actif',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.agriculteurs : ~3 rows (environ)
INSERT INTO `agriculteurs` (`id`, `nom`, `prenom`, `telephone`, `email`, `statut`, `created_at`) VALUES
	(1, 'Ben Ali', 'Mohamed', '+21698765432', 'mohamed.benali@email.com', 'actif', '2026-06-09 14:00:58'),
	(2, 'Trabelsi', 'Amira', '+21655123456', 'amira.trabelsi@email.com', 'actif', '2026-06-09 14:00:58'),
	(4, 'aoua', 'aroua', '50875531', 'ghofrane.aroua@hotmail.com', 'actif', '2026-06-27 09:56:45'),
	(9, 'aoua', 'uuuu', '50875531', 'popo@hotmail.com', 'actif', '2026-06-27 10:25:15');

-- Listage de la structure de table water_db. alertes
CREATE TABLE IF NOT EXISTS `alertes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `borne_id` int NOT NULL,
  `type_alerte` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_heure` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resolu` tinyint(1) DEFAULT '0',
  `date_resolution` timestamp NULL DEFAULT NULL COMMENT 'Date de résolution de l''alerte',
  `source` enum('http','mqtt') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'http' COMMENT 'Canal de réception de l''alerte (http REST ou mqtt)',
  PRIMARY KEY (`id`),
  KEY `borne_id` (`borne_id`),
  CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`borne_id`) REFERENCES `bornes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.alertes : ~19 rows (environ)
INSERT INTO `alertes` (`id`, `borne_id`, `type_alerte`, `description`, `date_heure`, `resolu`, `date_resolution`, `source`) VALUES
	(1, 1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', '2026-06-09 08:00:58', 1, NULL, 'http'),
	(2, 1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', '2026-06-10 04:55:06', 1, NULL, 'http'),
	(3, 1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', '2026-06-10 04:55:06', 1, NULL, 'http'),
	(4, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-17 10:09:42', 0, NULL, 'http'),
	(5, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-17 10:09:42', 0, NULL, 'http'),
	(6, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-17 10:09:56', 0, NULL, 'http'),
	(7, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-17 10:09:56', 0, NULL, 'http'),
	(8, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-19 09:58:41', 0, NULL, 'http'),
	(9, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-19 09:59:31', 0, NULL, 'http'),
	(10, 1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', '2026-06-25 16:58:20', 1, NULL, 'http'),
	(11, 1, 'trop_plein', 'Coupure automatique : Capteur de trop-plein activé pendant la distribution de la carte 04B76C982A1E40', '2026-06-25 16:58:20', 1, NULL, 'http'),
	(12, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-25 23:05:08', 0, NULL, 'http'),
	(13, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-25 23:05:08', 0, NULL, 'http'),
	(14, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 23:07:53', '2026-06-25 23:07:53', 1, NULL, 'http'),
	(15, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 23:07:54', '2026-06-25 23:07:54', 1, NULL, 'http'),
	(16, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 08:54:41', '2026-06-26 08:54:41', 1, NULL, 'http'),
	(17, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 10:43:35', '2026-06-27 10:43:35', 1, NULL, 'http'),
	(18, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-27 10:44:55', 0, NULL, 'http'),
	(19, 1, 'fuite', 'Test simulation : Débit suspect persistant.', '2026-06-27 10:44:55', 0, NULL, 'http'),
	(20, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 10:45:00', '2026-06-27 10:45:00', 1, NULL, 'http'),
	(21, 1, 'fuite', '[TEST AUTOMATIQUE] Simulation fuite — 10:45:01', '2026-06-27 10:45:01', 1, NULL, 'http');

-- Listage de la structure de table water_db. bornes
CREATE TABLE IF NOT EXISTS `bornes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `localisation` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut_connexion` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'hors ligne',
  `dernier_ping` timestamp NULL DEFAULT NULL,
  `etat_vanne` enum('ouverte','fermee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fermee' COMMENT 'Dernier état connu de la vanne (via MQTT)',
  `debit_instantane` float DEFAULT NULL COMMENT 'Dernier débit instantané connu en L/min (via MQTT)',
  `capteur_fuite` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = fuite sol détectée (via MQTT)',
  `capteur_trop_plein` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = trop-plein détecté (via MQTT)',
  `derniere_telemerie_mqtt` timestamp NULL DEFAULT NULL COMMENT 'Timestamp de la dernière télémétrie MQTT reçue',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.bornes : ~2 rows (environ)
INSERT INTO `bornes` (`id`, `nom`, `localisation`, `statut_connexion`, `dernier_ping`, `etat_vanne`, `debit_instantane`, `capteur_fuite`, `capteur_trop_plein`, `derniere_telemerie_mqtt`) VALUES
	(1, 'Borne Secteur Nord (Cuve principale)', 'Zone Agricole Nord - Secteur A', 'en ligne', '2026-06-27 10:45:01', 'fermee', NULL, 0, 0, NULL),
	(2, 'Borne Secteur Sud', 'Plaine du Sud - Secteur C', 'hors ligne', '2026-06-09 12:00:58', 'fermee', NULL, 0, 0, NULL);

-- Listage de la structure de table water_db. cartes_rfid
CREATE TABLE IF NOT EXISTS `cartes_rfid` (
  `uid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `agriculteur_id` int NOT NULL,
  `solde` decimal(10,2) NOT NULL DEFAULT '0.00',
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `code_pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Code PIN 4 chiffres hashé bcrypt',
  `date_association` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_utilisation` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uid`),
  KEY `agriculteur_id` (`agriculteur_id`),
  CONSTRAINT `cartes_rfid_ibfk_1` FOREIGN KEY (`agriculteur_id`) REFERENCES `agriculteurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.cartes_rfid : ~3 rows (environ)
INSERT INTO `cartes_rfid` (`uid`, `agriculteur_id`, `solde`, `statut`, `code_pin`, `date_association`, `derniere_utilisation`) VALUES
	('047859623', 9, 100.00, 'active', '7777', '2026-06-27 10:25:15', NULL),
	('04A32B125C6D80', 1, 148.00, 'active', '1234', '2026-06-09 14:00:58', '2026-06-27 10:45:01'),
	('04B76C982A1E40', 2, 0.10, 'active', '5555', '2026-06-09 14:00:58', '2026-06-23 23:49:43'),
	('64856132165', 4, 50.00, 'active', '4014', '2026-06-27 09:56:45', NULL);

-- Listage de la structure de table water_db. commandes_bornes
CREATE TABLE IF NOT EXISTS `commandes_bornes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `borne_id` int NOT NULL,
  `type_commande` varchar(50) NOT NULL COMMENT 'Type de commande envoyée à la borne',
  `statut` enum('en_attente','envoyee','ack','echec') NOT NULL DEFAULT 'en_attente' COMMENT 'Cycle de vie de la commande',
  `demande_par` int DEFAULT NULL COMMENT 'ID de l''administrateur ayant émis la commande',
  `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_envoi` timestamp NULL DEFAULT NULL COMMENT 'Horodatage de la publication MQTT par le worker',
  `date_ack` timestamp NULL DEFAULT NULL COMMENT 'Horodatage de l''accusé de réception ESP32',
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  KEY `idx_borne_id` (`borne_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Journal des commandes de pompe envoyées aux bornes via MQTT';

-- Listage des données de la table water_db.commandes_bornes : ~1 rows (environ)
INSERT INTO `commandes_bornes` (`id`, `borne_id`, `type_commande`, `statut`, `demande_par`, `date_creation`, `date_envoi`, `date_ack`) VALUES
	(1, 1, 'start_pompe', 'en_attente', 1, '2026-06-24 11:43:06', NULL, NULL);

-- Listage de la structure de table water_db. configurations
CREATE TABLE IF NOT EXISTS `configurations` (
  `param_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `param_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`param_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.configurations : ~0 rows (environ)
INSERT INTO `configurations` (`param_key`, `param_value`, `description`, `updated_at`) VALUES
	('price_per_litre', '0.200', 'Prix de l\'eau en Euros par Litre', '2026-06-10 10:55:06');

-- Listage de la structure de table water_db. transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `carte_rfid_uid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `borne_id` int NOT NULL,
  `volume_demande` decimal(10,2) NOT NULL,
  `volume_distribue` decimal(10,2) NOT NULL,
  `montant_debite` decimal(10,2) NOT NULL,
  `date_heure` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'reussie',
  PRIMARY KEY (`id`),
  KEY `carte_rfid_uid` (`carte_rfid_uid`),
  KEY `borne_id` (`borne_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`carte_rfid_uid`) REFERENCES `cartes_rfid` (`uid`) ON DELETE RESTRICT,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`borne_id`) REFERENCES `bornes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Listage des données de la table water_db.transactions : ~29 rows (environ)
INSERT INTO `transactions` (`id`, `carte_rfid_uid`, `borne_id`, `volume_demande`, `volume_distribue`, `montant_debite`, `date_heure`, `statut`) VALUES
	(1, '04A32B125C6D80', 1, 500.00, 500.00, 5.00, '2026-06-08 14:00:58', 'reussie'),
	(2, '04A32B125C6D80', 1, 1000.00, 1000.00, 10.00, '2026-06-09 02:00:58', 'reussie'),
	(3, '04B76C982A1E40', 1, 200.00, 185.50, 1.86, '2026-06-09 08:00:58', 'interrompue_erreur'),
	(4, '04A32B125C6D80', 1, 50.00, 50.00, 10.00, '2026-06-09 10:55:06', 'reussie'),
	(5, '04A32B125C6D80', 1, 100.00, 100.00, 20.00, '2026-06-09 22:55:06', 'reussie'),
	(6, '04B76C982A1E40', 1, 20.00, 18.50, 3.70, '2026-06-10 04:55:06', 'interrompue_erreur'),
	(7, '04A32B125C6D80', 1, 50.00, 50.00, 10.00, '2026-06-09 10:55:06', 'reussie'),
	(8, '04A32B125C6D80', 1, 100.00, 100.00, 20.00, '2026-06-09 22:55:06', 'reussie'),
	(9, '04B76C982A1E40', 1, 20.00, 18.50, 3.70, '2026-06-10 04:55:06', 'interrompue_erreur'),
	(10, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-17 10:09:56', 'reussie'),
	(11, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-17 10:09:56', 'reussie'),
	(12, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-19 09:58:41', 'reussie'),
	(13, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-19 09:59:31', 'reussie'),
	(14, '04A32B125C6D80', 1, 150.00, 150.00, 30.00, '2026-06-23 09:23:54', 'reussie'),
	(15, '04B76C982A1E40', 1, 127.00, 127.00, 25.40, '2026-06-23 23:49:43', 'reussie'),
	(16, '04A32B125C6D80', 1, 150.00, 150.00, 30.00, '2026-06-23 23:49:51', 'reussie'),
	(17, '04A32B125C6D80', 1, 50.00, 50.00, 10.00, '2026-06-24 22:58:20', 'reussie'),
	(18, '04A32B125C6D80', 1, 100.00, 100.00, 20.00, '2026-06-25 10:58:20', 'reussie'),
	(19, '04B76C982A1E40', 1, 20.00, 18.50, 3.70, '2026-06-25 16:58:20', 'interrompue_erreur'),
	(20, '04A32B125C6D80', 1, 50.00, 50.00, 10.00, '2026-06-24 22:58:20', 'reussie'),
	(21, '04A32B125C6D80', 1, 100.00, 100.00, 20.00, '2026-06-25 10:58:20', 'reussie'),
	(22, '04B76C982A1E40', 1, 20.00, 18.50, 3.70, '2026-06-25 16:58:20', 'interrompue_erreur'),
	(23, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-25 23:07:53', 'reussie'),
	(24, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-25 23:07:54', 'reussie'),
	(25, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-26 08:54:41', 'reussie'),
	(26, '04A32B125C6D80', 1, 540.00, 540.00, 108.00, '2026-06-27 10:32:22', 'reussie'),
	(27, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-27 10:43:35', 'reussie'),
	(28, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-27 10:44:55', 'reussie'),
	(29, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-27 10:44:55', 'reussie'),
	(30, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-27 10:45:00', 'reussie'),
	(31, '04A32B125C6D80', 1, 10.00, 10.00, 2.00, '2026-06-27 10:45:01', 'reussie');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
