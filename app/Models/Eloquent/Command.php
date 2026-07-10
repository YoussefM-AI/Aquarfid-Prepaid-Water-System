<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for Command (coexists with commandes_bornes)
 */
class Command extends Model
{
    protected $table = 'commands';
    public $timestamps = false; // Custom datetime tracking (created_at, sent_at, ack_at)

    protected $fillable = [
        'station_id',
        'command_type',
        'payload',
        'status',
        'created_by',
        'created_at',
        'sent_at',
        'ack_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'sent_at' => 'datetime',
        'ack_at' => 'datetime'
    ];

    /**
     * Get the station target for this command.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    /**
     * Get the admin user who created this command.
     */
    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
