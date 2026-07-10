<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for MqttMessage
 */
class MqttMessage extends Model
{
    protected $table = 'mqtt_messages';
    public $timestamps = false; // Only created_at is needed

    protected $fillable = [
        'topic',
        'payload',
        'direction'
    ];

    protected $casts = [
        'created_at' => 'datetime'
    ];
}
