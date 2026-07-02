-- Migration : Ajout du code PIN aux cartes RFID
-- Exécuter une seule fois dans phpMyAdmin ou via la CLI MySQL

USE `water_db`;

-- Ajouter la colonne code_pin (nullable, hashé avec bcrypt)
ALTER TABLE `cartes_rfid`
    ADD COLUMN `code_pin` VARCHAR(255) NULL DEFAULT NULL
        COMMENT 'Code PIN à 4 chiffres hashé (bcrypt) - NULL si pas de PIN défini'
    AFTER `statut`;

-- Exemples de PINs de test pour les 3 cartes demo
-- PIN 1234 pour Mohamed Ben Ali (carte 04A32B125C6D80)
-- PIN 5678 pour Amira Trabelsi  (carte 04B76C982A1E40)
-- (Salem est bloqué, pas de PIN)

-- Générer les hashes via PHP : echo password_hash('1234', PASSWORD_BCRYPT);
-- Les valeurs ci-dessous sont les hashes bcrypt corrects pour les PINs de test

UPDATE `cartes_rfid`
SET `code_pin` = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE `uid` = '04A32B125C6D80';
-- Mot de passe: 'password' (bcrypt standard de test Laravel)
-- REMPLACER par le bon hash de '1234' généré par PHP

-- Pour générer les vrais hashes, exécuter ce script PHP une fois:
-- <?php echo password_hash('1234', PASSWORD_BCRYPT); ?>  => pour Mohamed
-- <?php echo password_hash('5678', PASSWORD_BCRYPT); ?>  => pour Amira
