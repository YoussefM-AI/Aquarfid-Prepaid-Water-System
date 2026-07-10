<?php

namespace App\Models;

use PDO;

/**
 * Model DeviceStatus
 * Logs device status reports and telemetry histories.
 */
class DeviceStatus extends BaseModel {
    /**
     * Insert a status log entry.
     */
    public function insert(array $data): bool {
        $stmt = $this->db->prepare(
            "INSERT INTO device_status (station_id, connection_status, rssi, valve_status, pump_status, flow_rate, volume_distributed, uptime, error_message)
             VALUES (:station_id, :connection_status, :rssi, :valve_status, :pump_status, :flow_rate, :volume_distributed, :uptime, :error_message)"
        );
        return $stmt->execute([
            'station_id'        => (int)$data['station_id'],
            'connection_status' => $data['connection_status'] ?? 'disconnected',
            'rssi'              => isset($data['rssi']) ? (int)$data['rssi'] : null,
            'valve_status'      => $data['valve_status'] ?? 'closed',
            'pump_status'       => $data['pump_status'] ?? 'OFF',
            'flow_rate'         => isset($data['flow_rate']) ? (float)$data['flow_rate'] : 0.0,
            'volume_distributed'=> isset($data['volume_distributed']) ? (float)$data['volume_distributed'] : 0.0,
            'uptime'            => isset($data['uptime']) ? (int)$data['uptime'] : 0,
            'error_message'     => $data['error_message'] ?? null
        ]);
    }

    /**
     * Retrieve the last status for a station.
     */
    public function getLatest(int $stationId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM device_status WHERE station_id = :station_id ORDER BY last_heartbeat DESC LIMIT 1"
        );
        $stmt->execute(['station_id' => $stationId]);
        return $stmt->fetch();
    }
}
