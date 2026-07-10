<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for Station (coexists with bornes)
 */
class Station extends Model
{
    protected $table = 'stations';

    protected $fillable = [
        'name',
        'location',
        'status',
        'api_key',
        'last_ping',
        'valve_status',
        'flow_rate',
        'volume_distributed'
    ];

    protected $casts = [
        'last_ping' => 'datetime',
        'flow_rate' => 'float',
        'volume_distributed' => 'float'
    ];

    /**
     * Get the device status records for this station.
     */
    public function deviceStatuses()
    {
        return $this->hasMany(DeviceStatus::class, 'station_id');
    }

    /**
     * Get the pump logs for this station.
     */
    public function pumpLogs()
    {
        return $this->hasMany(PumpLog::class, 'station_id');
    }

    /**
     * Get the alerts for this station.
     */
    public function alerts()
    {
        return $this->hasMany(Alert::class, 'station_id');
    }

    /**
     * Get the transactions for this station.
     */
    public function transactions()
    {
        return $this->hasMany(WaterTransaction::class, 'station_id');
    }

    /**
     * Get the commands sent to this station.
     */
    public function commands()
    {
        return $this->hasMany(Command::class, 'station_id');
    }
}
