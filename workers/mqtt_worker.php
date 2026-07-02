<?php

/**
 * ====================================================================
 * WORKER MQTT — Pont MariaDB ↔ Mosquitto pour bornes RFID eau
 * ====================================================================
 * Lancement : php workers/mqtt_worker.php
 *
 * Ce script tourne en boucle infinie (daemon). Il :
 *   - Se connecte au broker Mosquitto
 *   - S'abonne aux topics eau/bornes/+/etat et eau/bornes/+/alerte
 *   - Met à jour MariaDB avec les télémétries reçues des ESP32
 *   - Poll la table commandes_bornes toutes les secondes et publie
 *     les commandes en attente sur MQTT
 *
 * Supervision recommandée (Windows) : NSSM ou pm2
 *   nssm install MqttWorker "php" "c:\laragon\www\water\workers\mqtt_worker.php"
 *   nssm start MqttWorker
 * ====================================================================
 */

declare(strict_types=1);

// ── Autoloader Composer ───────────────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("[FATAL] vendor/autoload.php introuvable. Lancez : composer install\n");
}
require_once $autoload;

// ── Classes du projet (PSR-4 via Composer ou require manuel) ──────────────────
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/Borne.php';
require_once __DIR__ . '/../app/Models/Alerte.php';
require_once __DIR__ . '/../app/Models/CommandeBorne.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use App\Models\Borne;
use App\Models\Alerte;
use App\Models\CommandeBorne;

// ── Configuration MQTT ────────────────────────────────────────────────────────
// Adaptez ces valeurs à votre installation Mosquitto locale
define('MQTT_HOST',      '127.0.0.1');
define('MQTT_PORT',      1883);
define('MQTT_CLIENT_ID', 'php_water_worker_' . gethostname());
define('MQTT_USERNAME',  '');           // Laisser vide si pas d'auth Mosquitto
define('MQTT_PASSWORD',  '');           // Laisser vide si pas d'auth Mosquitto
define('MQTT_KEEPALIVE', 60);           // Secondes

// ── Topics ────────────────────────────────────────────────────────────────────
define('TOPIC_ETAT',         'eau/bornes/+/etat');
define('TOPIC_ALERTE',       'eau/bornes/+/alerte');
define('TOPIC_COMMANDE_SUB', 'eau/bornes/+/commande');

// ── Timing ────────────────────────────────────────────────────────────────────
define('POLL_INTERVAL_MS',  1000);   // Polling commandes (ms)
define('RECONNECT_DELAY_S', 5);      // Délai avant tentative reconnexion

// ── Log ───────────────────────────────────────────────────────────────────────
define('LOG_FILE', __DIR__ . '/mqtt_worker.log');
define('LOG_MAX_SIZE_MB', 10);       // Rotation si > 10 Mo

// =============================================================================
// Fonctions utilitaires
// =============================================================================

function workerLog(string $level, string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    echo $line;
    // Écriture fichier log avec rotation simple
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_SIZE_MB * 1024 * 1024) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Ymd_His') . '.old');
    }
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Extrait le borne_id depuis un topic MQTT.
 * Exemple : "eau/bornes/3/etat" → 3
 */
function extractBorneId(string $topic): ?int {
    if (preg_match('#eau/bornes/(\d+)/#', $topic, $m)) {
        return (int)$m[1];
    }
    return null;
}

// =============================================================================
// Gestionnaire de messages MQTT reçus
// =============================================================================

function handleMessage(string $topic, string $payload, Borne $borneModel, Alerte $alerteModel): void {
    $borneId = extractBorneId($topic);
    if ($borneId === null) {
        workerLog('warn', "Topic inattendu, impossible d'extraire borne_id : $topic");
        return;
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
        workerLog('warn', "Payload JSON invalide sur $topic : $payload");
        return;
    }

    // ── Topic eau/bornes/{id}/etat ────────────────────────────────────────────
    if (str_ends_with($topic, '/etat')) {
        workerLog('info', "Télémétrie borne #$borneId : " . json_encode($data));
        $ok = $borneModel->updateTelemetry($borneId, [
            'etat_vanne'         => $data['etat_vanne']      ?? 'fermee',
            'debit_instantane'   => $data['debit']           ?? null,
            'capteur_fuite'      => !empty($data['fuite'])   ? 1 : 0,
            'capteur_trop_plein' => !empty($data['trop_plein']) ? 1 : 0,
        ]);
        if (!$ok) {
            workerLog('error', "Échec updateTelemetry pour borne #$borneId");
        }
        return;
    }

    // ── Topic eau/bornes/{id}/alerte ──────────────────────────────────────────
    if (str_ends_with($topic, '/alerte')) {
        $typeAlerte  = trim($data['type']        ?? $data['type_alerte']  ?? '');
        $description = trim($data['description'] ?? $data['desc']         ?? '');

        if (empty($typeAlerte) || empty($description)) {
            workerLog('warn', "Alerte MQTT mal formée borne #$borneId : $payload");
            return;
        }

        workerLog('warn', "ALERTE MQTT borne #$borneId [$typeAlerte] : $description");
        $ok = $alerteModel->insertWithSource([
            'borne_id'    => $borneId,
            'type_alerte' => $typeAlerte,
            'description' => $description,
            'source'      => 'mqtt',
        ]);
        if (!$ok) {
            workerLog('error', "Échec insertWithSource pour borne #$borneId");
        }
        return;
    }

    workerLog('debug', "Topic non traité : $topic");
}

/**
 * Intercepte les commandes publiées sur MQTT (ex: par le dashboard)
 * et les consigne en base de données pour l'historique.
 */
function handleCommandMessage(string $topic, string $payload): void {
    $borneId = extractBorneId($topic);
    if ($borneId === null) {
        workerLog('warn', "Topic commande inattendu, impossible d'extraire borne_id : $topic");
        return;
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
        workerLog('warn', "Payload commande JSON invalide sur $topic : $payload");
        return;
    }

    $typeCommande = $data['type'] ?? '';
    if (!in_array($typeCommande, ['start_pompe', 'stop_pompe'], true)) {
        workerLog('warn', "Type commande inconnu : $typeCommande");
        return;
    }

    workerLog('info', "Interception commande borne #$borneId : $typeCommande");

    try {
        $db = \App\Core\Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            "INSERT INTO commandes_bornes (borne_id, type_commande, statut, date_creation, date_envoi)
             VALUES (:borne_id, :type_commande, 'envoyee', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            'borne_id'      => $borneId,
            'type_commande' => $typeCommande,
        ]);
        $cmdId = $db->lastInsertId();
        workerLog('info', "  Commande enregistrée en DB (ID: $cmdId, statut: envoyee)");
    } catch (\Throwable $e) {
        workerLog('error', "Échec de l'enregistrement de la commande en DB : " . $e->getMessage());
    }
}

// =============================================================================
// Boucle principale
// =============================================================================

workerLog('info', '====================================================');
workerLog('info', '  MQTT Worker — Borne RFID Eau — Démarrage');
workerLog('info', '  Broker : ' . MQTT_HOST . ':' . MQTT_PORT);
workerLog('info', '  Client ID : ' . MQTT_CLIENT_ID);
workerLog('info', '====================================================');

$borneModel    = new Borne();
$alerteModel   = new Alerte();
$commandeModel = new CommandeBorne();

$lastPollTime  = 0;

while (true) {
    try {
        // ── Connexion au broker ───────────────────────────────────────────────
        workerLog('info', 'Connexion au broker MQTT ' . MQTT_HOST . ':' . MQTT_PORT . ' ...');

        $connectionSettings = (new ConnectionSettings())
            ->setKeepAliveInterval(MQTT_KEEPALIVE)
            ->setConnectTimeout(10)
            ->setSocketTimeout(1);

        if (!empty(MQTT_USERNAME)) {
            $connectionSettings->setUsername(MQTT_USERNAME)
                               ->setPassword(MQTT_PASSWORD);
        }

        $client = new MqttClient(MQTT_HOST, MQTT_PORT, MQTT_CLIENT_ID);
        $client->connect($connectionSettings, true); // cleanSession = true

        workerLog('info', 'Connexion MQTT établie ✓');

        // ── Abonnements ───────────────────────────────────────────────────────
        $client->subscribe(TOPIC_ETAT, function (string $topic, string $payload) use ($borneModel, $alerteModel) {
            handleMessage($topic, $payload, $borneModel, $alerteModel);
        }, 0);

        $client->subscribe(TOPIC_ALERTE, function (string $topic, string $payload) use ($borneModel, $alerteModel) {
            handleMessage($topic, $payload, $borneModel, $alerteModel);
        }, 0);

        $client->subscribe(TOPIC_COMMANDE_SUB, function (string $topic, string $payload) {
            handleCommandMessage($topic, $payload);
        }, 0);

        workerLog('info', 'Abonnements actifs : ' . TOPIC_ETAT . ' | ' . TOPIC_ALERTE . ' | ' . TOPIC_COMMANDE_SUB);

        // ── Boucle de traitement ──────────────────────────────────────────────
        $client->loop(true);

    } catch (MqttClientException $e) {
        workerLog('error', 'Erreur MQTT : ' . $e->getMessage());
    } catch (\Throwable $e) {
        workerLog('error', 'Erreur inattendue : ' . $e->getMessage());
    }

    workerLog('info', 'Reconnexion dans ' . RECONNECT_DELAY_S . ' secondes...');
    sleep(RECONNECT_DELAY_S);
}
