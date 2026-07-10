<?php

namespace App\Models;

use PDO;

/**
 * Model PumpLog
 * Logs pump operation events.
 */
class PumpLog extends BaseModel {
    /**
     * Insert a pump event log.
     */
    public function log(int $stationId, string $action, float $volume = 0.0, int $duration = 0, string $triggeredBy = 'rfid'): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO pump_logs (station_id, action, volume, duration, triggered_by)
             VALUES (:station_id, :action, :volume, :duration, :triggered_by)"
        );
        return $stmt->execute([
            'station_id'   => $stationId,
            'action'       => $action, // 'ON' or 'OFF'
            'volume'       => $volume,
            'duration'     => $duration,
            'triggered_by' => $triggeredBy // 'rfid', 'admin', 'safety'
        ]);
    }

    /**
     * Get recent logs.
     */
    public function getRecent(int $limit = 20): array {
        $stmt = $this->db->prepare(
            "SELECT p.*, b.nom AS borne_nom 
             FROM pump_logs p
             LEFT JOIN bornes b ON p.station_id = b.id
             ORDER BY p.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
