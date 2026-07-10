<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for DeviceStatus
 */
class DeviceStatus extends Model
{
    protected $table = 'device_status';
    public $timestamps = false; // Uses custom created_at and last_heartbeat

    protected $fillable = [
        'station_id',
        'connection_status',
        'rssi',
        'valve_status',
        'pump_status',
        'flow_rate',
        'volume_distributed',
        'uptime',
        'error_message',
        'last_heartbeat'
    ];

    protected $casts = [
        'rssi' => 'integer',
        'flow_rate' => 'float',
        'volume_distributed' => 'float',
        'uptime' => 'integer',
        'last_heartbeat' => 'datetime',
        'created_at' => 'datetime'
    ];

    /**
     * Get the station for this status entry.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }
}
