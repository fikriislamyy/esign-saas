<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'organization_id',
        'subscription_id',
        'plan',
        'provider',
        'order_id',
        'currency',
        'amount',
        'amount_usd_cents',
        'exchange_rate',
        'status',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
