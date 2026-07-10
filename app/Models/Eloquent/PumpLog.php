<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for PumpLog
 */
class PumpLog extends Model
{
    protected $table = 'pump_logs';
    public $timestamps = false; // Only created_at is needed

    protected $fillable = [
        'station_id',
        'action',
        'volume',
        'duration',
        'triggered_by'
    ];

    protected $casts = [
        'volume' => 'float',
        'duration' => 'integer',
        'created_at' => 'datetime'
    ];

    /**
     * Get the station for this log entry.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }
}
