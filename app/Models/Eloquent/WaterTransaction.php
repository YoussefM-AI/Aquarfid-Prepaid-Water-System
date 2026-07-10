<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for WaterTransaction (coexists with transactions)
 */
class WaterTransaction extends Model
{
    protected $table = 'water_transactions';
    public $timestamps = false; // Database handles created_at default

    protected $fillable = [
        'card_uid',
        'station_id',
        'volume_requested',
        'volume_distributed',
        'amount_debited',
        'status',
        'created_at'
    ];

    protected $casts = [
        'volume_requested' => 'float',
        'volume_distributed' => 'float',
        'amount_debited' => 'float',
        'created_at' => 'datetime'
    ];

    /**
     * Get the station for this transaction.
     */
    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    /**
     * Get the RFID card for this transaction.
     */
    public function rfidCard()
    {
        return $this->belongsTo(RfidCard::class, 'card_uid', 'uid');
    }
}
