<?php

namespace App\Models;

use PDO;

/**
 * Model MqttMessage
 * Logs raw MQTT traffic.
 */
class MqttMessage extends BaseModel {
    /**
     * Insert a new MQTT log entry.
     */
    public function log(string $topic, string $payload, string $direction = 'in'): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO mqtt_messages (topic, payload, direction)
             VALUES (:topic, :payload, :direction)"
        );
        return $stmt->execute([
            'topic'     => $topic,
            'payload'   => $payload,
            'direction' => $direction
        ]);
    }

    /**
     * Retrieve recent MQTT messages for UI display.
     */
    public function getRecent(int $limit = 50): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM mqtt_messages ORDER BY created_at DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
