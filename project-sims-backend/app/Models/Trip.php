<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reservation_id',
        'engine_started_at',
        'engine_stopped_at',
        'total_amount',
        'penalty_amount',
        'minutes_driven',
        'start_location',
        'end_location',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'float',
        'penalty_amount' => 'float',
        'minutes_driven' => 'integer',
        'engine_started_at' => 'datetime',
        'engine_stopped_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}