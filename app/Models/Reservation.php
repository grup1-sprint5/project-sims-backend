<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'scheduled_start',
        'scheduled_end',
        'activation_deadline',
        'total_price',
        'payment_provider',
        'payment_status',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'paid_at',
        'cancelled_at',
        'cancellation_fee',
        'status'
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'activation_deadline' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_price' => 'float',
        'cancellation_fee' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trip()
    {
        return $this->hasOne(Trip::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'active']);
    }

    public function scopePendingAndOverdue($query, ?Carbon $referenceTime = null)
    {
        $now = $referenceTime ?? now();
        $graceLimit = $now->copy()->subMinutes(20);

        return $query
            ->where('status', 'pending')
            ->where(function ($innerQuery) use ($now, $graceLimit) {
                $innerQuery
                    ->where(function ($deadlineQuery) use ($now) {
                        $deadlineQuery
                            ->whereNotNull('activation_deadline')
                            ->where('activation_deadline', '<=', $now);
                    })
                    ->orWhere(function ($legacyQuery) use ($graceLimit) {
                        $legacyQuery
                            ->whereNull('activation_deadline')
                            ->whereNotNull('scheduled_start')
                            ->where('scheduled_start', '<=', $graceLimit);
                    })
                    ->orWhere(function ($endQuery) use ($now) {
                        $endQuery
                            ->whereNotNull('scheduled_end')
                            ->where('scheduled_end', '<=', $now);
                    });
            });
    }

    public function scopeInvalidActiveWithoutTrip($query, ?Carbon $referenceTime = null)
    {
        $now = $referenceTime ?? now();
        $graceLimit = $now->copy()->subMinutes(20);

        return $query
            ->where('status', 'active')
            ->doesntHave('trip')
            ->where(function ($innerQuery) use ($now, $graceLimit) {
                $innerQuery
                    ->where(function ($deadlineQuery) use ($now) {
                        $deadlineQuery
                            ->whereNotNull('activation_deadline')
                            ->where('activation_deadline', '<=', $now);
                    })
                    ->orWhere(function ($legacyQuery) use ($graceLimit) {
                        $legacyQuery
                            ->whereNull('activation_deadline')
                            ->whereNotNull('scheduled_start')
                            ->where('scheduled_start', '<=', $graceLimit);
                    });
            });
    }

    public function isPendingAndOverdue(?Carbon $referenceTime = null): bool
    {
        $now = $referenceTime ?? now();

        if ($this->status !== 'pending') {
            return false;
        }

        if ($this->activation_deadline !== null) {
            return $this->activation_deadline->lte($now);
        }

        if ($this->scheduled_end !== null) {
            return $this->scheduled_end->lte($now);
        }

        if ($this->scheduled_start !== null) {
            return $this->scheduled_start->lte($now->copy()->subMinutes(20));
        }

        return false;
    }

    public function markAsExpired(?Carbon $referenceTime = null): void
    {
        $now = $referenceTime ?? now();

        $this->update([
            'status' => 'expired',
            'cancelled_at' => $now,
        ]);
    }

    public function isInvalidActiveWithoutTrip(?Carbon $referenceTime = null): bool
    {
        $now = $referenceTime ?? now();

        if ($this->status !== 'active' || $this->trip !== null) {
            return false;
        }

        if ($this->activation_deadline !== null) {
            return $this->activation_deadline->lte($now);
        }

        if ($this->scheduled_start !== null) {
            return $this->scheduled_start->lte($now->copy()->subMinutes(20));
        }

        return false;
    }
}