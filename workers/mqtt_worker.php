<?php

/**
 * ====================================================================
 * WORKER MQTT — Pont MariaDB ↔ Mosquitto pour bornes RFID eau
 * ====================================================================
 * Lancement : php workers/mqtt_worker.php
 *
 * Ce script tourne en boucle infinie (daemon). Il :
 *   - Se connecte au broker Mosquitto
 *   - S'abonne aux nouveaux topics : water/station+/+
 *   - Met à jour MariaDB avec les télémétries et heartbeats
 *   - Écrit dans les nouvelles tables : mqtt_messages, device_status, pump_logs
 *   - Poll la table commandes_bornes et publie les commandes en attente
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
require_once __DIR__ . '/../app/Models/MqttMessage.php';
require_once __DIR__ . '/../app/Models/DeviceStatus.php';
require_once __DIR__ . '/../app/Models/PumpLog.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use App\Models\Borne;
use App\Models\Alerte;
use App\Models\CommandeBorne;
use App\Models\MqttMessage;
use App\Models\DeviceStatus;
use App\Models\PumpLog;

// ── Configuration MQTT ────────────────────────────────────────────────────────
define('MQTT_HOST',      '127.0.0.1');  // Broker Mosquitto local (Laragon)
define('MQTT_PORT',      1883);
define('MQTT_CLIENT_ID', 'php_water_worker_' . gethostname() . '_' . uniqid());
define('MQTT_USERNAME',  'water_user');
define('MQTT_PASSWORD',  'water_pass123');
define('MQTT_KEEPALIVE', 60);           // Secondes

// ── Topics ────────────────────────────────────────────────────────────────────
define('TOPIC_WILDCARD', 'water/+/+');

// ── Timing ────────────────────────────────────────────────────────────────────
define('POLL_INTERVAL_S',   1);      // Polling commandes (secondes)
define('RECONNECT_DELAY_S', 5);      // Reconnexion (secondes)

// ── Log ───────────────────────────────────────────────────────────────────────
define('LOG_FILE', __DIR__ . '/mqtt_worker.log');
define('LOG_MAX_SIZE_MB', 10);

// =============================================================================
// Fonctions utilitaires
// =============================================================================

function workerLog(string $level, string $message): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    echo $line;
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_SIZE_MB * 1024 * 1024) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Ymd_His') . '.old');
    }
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Extrait le borne_id (station_id) depuis un topic.
 * Exemple : "water/station1/status" -> 1
 */
function extractStationId(string $topic): ?int {
    if (preg_match('#water/station(\d+)/#', $topic, $m)) {
        return (int)$m[1];
    }
    return null;
}

// =============================================================================
// Gestionnaire de messages MQTT reçus
// =============================================================================

function handleMessage(
    string $topic, 
    string $payload, 
    Borne $borneModel, 
    Alerte $alerteModel,
    MqttMessage $mqttMessageModel,
    DeviceStatus $deviceStatusModel,
    PumpLog $pumpLogModel
): void {
    // 1. Enregistrer dans la table mqtt_messages
    $mqttMessageModel->log($topic, $payload, 'in');

    $stationId = extractStationId($topic);
    if ($stationId === null) {
        workerLog('warn', "Impossible d'extraire station_id de : $topic");
        return;
    }

    $subTopic = substr($topic, strrpos($topic, '/') + 1);

    // 2. Traitement selon le sous-topic
    switch ($subTopic) {
        case 'status':
            workerLog('info', "Statut Station #$stationId : $payload");
            $connStatut = ($payload === 'online') ? 'en ligne' : 'hors ligne';
            
            // Mise à jour de la table bornes (qui synchronise stations via trigger)
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE bornes SET statut_connexion = :statut, dernier_ping = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute(['statut' => $connStatut, 'id' => $stationId]);

            // Enregistrer dans device_status
            $deviceStatusModel->insert([
                'station_id' => $stationId,
                'connection_status' => $payload,
                'error_message' => ($payload === 'offline') ? 'Device went offline (LWT)' : null
            ]);
            break;

        case 'flow':
            $flowRate = (float)$payload;
            workerLog('debug', "Débit Station #$stationId : $flowRate L/min");
            
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE bornes SET debit_instantane = :debit, dernier_ping = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute(['debit' => $flowRate, 'id' => $stationId]);
            break;

        case 'volume':
            $volume = (float)$payload;
            workerLog('debug', "Volume Station #$stationId : $volume L");
            
            $deviceStatusModel->insert([
                'station_id' => $stationId,
                'connection_status' => 'connected',
                'volume_distributed' => $volume
            ]);
            break;

        case 'pump':
            workerLog('info', "Pompe Station #$stationId : $payload");
            $vanneStatut = ($payload === 'ON') ? 'ouverte' : 'fermee';
            
            $db = \App\Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE bornes SET etat_vanne = :vanne, dernier_ping = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute(['vanne' => $vanneStatut, 'id' => $stationId]);

            // Log de la pompe
            $pumpLogModel->log($stationId, $payload, 0.0, 0, 'rfid');
            break;

        case 'heartbeat':
            $data = json_decode($payload, true);
            if (is_array($data)) {
                $rssi = $data['rssi'] ?? null;
                $uptime = $data['uptime'] ?? 0;
                workerLog('info', "Heartbeat Station #$stationId (RSSI: $rssi, Uptime: $uptime ms)");
                
                // Mettre à jour bornes ping
                $borneModel->ping($stationId);

                // Enregistrer dans device_status
                $deviceStatusModel->insert([
                    'station_id' => $stationId,
                    'connection_status' => 'connected',
                    'rssi' => $rssi,
                    'uptime' => $uptime
                ]);
            }
            break;

        case 'log':
            $data = json_decode($payload, true);
            $level = $data['level'] ?? 'info';
            $msg = $data['message'] ?? $payload;
            workerLog('warn', "LOG Station #$stationId [$level] : $msg");

            if ($level === 'error' || $level === 'alert') {
                $alerteModel->insertWithSource([
                    'borne_id'    => $stationId,
                    'type_alerte' => 'technique',
                    'description' => "[MQTT] " . $msg,
                    'source'      => 'mqtt'
                ]);
            }
            break;

        case 'rfid':
            workerLog('info', "RFID scanné Station #$stationId : $payload");
            break;

        default:
            workerLog('debug', "Topic non géré : $topic (payload: $payload)");
            break;
    }
}

// =============================================================================
// Polling et Envoi des commandes
// =============================================================================

function pollAndPublishCommands(MqttClient $client, CommandeBorne $commandeModel, MqttMessage $mqttMessageModel): void {
    $pending = $commandeModel->findPending();
    if (empty($pending)) {
        return;
    }

    foreach ($pending as $cmd) {
        $cmdId = (int)$cmd['id'];
        $stationId = (int)$cmd['borne_id'];
        $type = $cmd['type_commande'];
        $volume = null;

        // Si la commande contient des paramètres (ex: set_volume:150)
        if (strpos($type, ':') !== false) {
            $parts = explode(':', $type);
            $type = $parts[0];
            $volume = (float)$parts[1];
        }

        workerLog('info', "Traitement commande #$cmdId ($type" . ($volume !== null ? " : {$volume}L" : "") . ") pour Station #$stationId...");

        // Formater le payload pour l'ESP32
        $payloadData = [
            'id' => $cmdId,
            'type' => $type,
            'ts' => date('c')
        ];

        if ($volume !== null) {
            $payloadData['volume'] = $volume;
        }
        
        $payloadJson = json_encode($payloadData);
        $topic = "water/station" . $stationId . "/command";

        try {
            // Publier en QoS 1 pour assurer la délivrance
            $client->publish($topic, $payloadJson, 1, false);
            workerLog('info', "  Publié sur $topic : $payloadJson");

            // Enregistrer dans mqtt_messages
            $mqttMessageModel->log($topic, $payloadJson, 'out');

            // Marquer comme envoyée
            $commandeModel->markEnvoyee($cmdId);
        } catch (\Throwable $e) {
            workerLog('error', "  Échec de publication de la commande #$cmdId : " . $e->getMessage());
            $commandeModel->markEchec($cmdId);
        }
    }
}

// =============================================================================
// Boucle principale
// =============================================================================

workerLog('info', '====================================================');
workerLog('info', '  MQTT Worker (English Scheme) — Démarrage');
workerLog('info', '  Broker : ' . MQTT_HOST . ':' . MQTT_PORT);
workerLog('info', '  Client ID : ' . MQTT_CLIENT_ID);
workerLog('info', '====================================================');

$borneModel        = new Borne();
$alerteModel       = new Alerte();
$commandeModel     = new CommandeBorne();
$mqttMessageModel  = new MqttMessage();
$deviceStatusModel = new DeviceStatus();
$pumpLogModel      = new PumpLog();

$lastCommandPoll = 0;

while (true) {
    try {
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
        $client->connect($connectionSettings, true);

        workerLog('info', 'Connexion MQTT établie ✓');

        // S'abonner aux topics water/+/+
        $client->subscribe(TOPIC_WILDCARD, function (string $topic, string $payload) use (
            $borneModel, $alerteModel, $mqttMessageModel, $deviceStatusModel, $pumpLogModel
        ) {
            handleMessage($topic, $payload, $borneModel, $alerteModel, $mqttMessageModel, $deviceStatusModel, $pumpLogModel);
        }, 1);

        workerLog('info', 'Abonnement actif sur : ' . TOPIC_WILDCARD);

        // Boucle non-bloquante pour permettre le polling de la base de données
        while ($client->isConnected()) {
            // Gérer les paquets MQTT entrants
            $client->loopOnce(microtime(true), false);

            // Polling des commandes de la base de données
            $now = time();
            if ($now - $lastCommandPoll >= POLL_INTERVAL_S) {
                pollAndPublishCommands($client, $commandeModel, $mqttMessageModel);
                $lastCommandPoll = $now;
            }

            // Attente de 100ms
            usleep(100000);
        }

    } catch (MqttClientException $e) {
        workerLog('error', 'Erreur MQTT : ' . $e->getMessage());
    } catch (\Throwable $e) {
        workerLog('error', 'Erreur inattendue : ' . $e->getMessage());
    }

    workerLog('info', 'Reconnexion dans ' . RECONNECT_DELAY_S . ' secondes...');
    sleep(RECONNECT_DELAY_S);
}
