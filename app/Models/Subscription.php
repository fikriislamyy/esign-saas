<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'plan',
        'status',
        'provider',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscribed_at',
        'expired_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The plan whose limits actually apply right now.
     *
     * A lapsed paid plan falls back to free rather than staying paid —
     * this is what stops an expired Pakasir subscription running forever
     * (decision 3.2).
     */
    public function effectivePlan(): string
    {
        if ($this->plan === 'free') {
            return 'free';
        }

        if ($this->status === 'cancelled') {
            return 'free';
        }

        if ($this->expired_at && $this->expired_at->isPast()) {
            return 'free';
        }

        return $this->plan;
    }

    public function config(): array
    {
        return config('plans.'.$this->effectivePlan());
    }
}
