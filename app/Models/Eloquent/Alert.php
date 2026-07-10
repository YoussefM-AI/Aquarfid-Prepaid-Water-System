<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for Alert (coexists with alertes)
 */
class Alert extends Model
{
    protected $table = 'alerts';
    public $timestamps = false; // Custom datetime fields (created_at, resolved_at)

    protected $fillable = [
        'station_id',
        'alert_type',
        'description',
        'resolved',
        'resolved_at',
        'source',
        'created_at'
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime'
    ];

    /**
     * Get the station associated with the alert.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }
}
