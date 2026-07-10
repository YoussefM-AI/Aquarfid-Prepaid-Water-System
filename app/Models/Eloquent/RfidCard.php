<?php

namespace App\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Eloquent model for RfidCard (coexists with cartes_rfid)
 */
class RfidCard extends Model
{
    use SoftDeletes;

    protected $table = 'rfid_cards';
    protected $primaryKey = 'uid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uid',
        'farmer_id',
        'balance',
        'status',
        'pin_code',
        'associated_at',
        'last_used_at'
    ];

    protected $casts = [
        'balance' => 'float',
        'associated_at' => 'datetime',
        'last_used_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the farmer that owns this card.
     */
    public function farmer()
    {
        return $this->belongsTo(Farmer::class, 'farmer_id');
    }

    /**
     * Get the transactions for this RFID card.
     */
    public function transactions()
    {
        return $this->hasMany(WaterTransaction::class, 'card_uid', 'uid');
    }
}
