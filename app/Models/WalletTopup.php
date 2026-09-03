<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopup extends Model
{
    protected $fillable = [
        'wallet_id',
        'organization_id',
        'currency',
        'amount',
        'exchange_rate',
        'wallet_amount_usd_cents',
        'stripe_checkout_session_id',
        'stripe_payment_intent_id',
        'status',
        'created_by',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'exchange_rate' => 'decimal:12',
        'wallet_amount_usd_cents' => 'integer',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Wallet
    |--------------------------------------------------------------------------
    */

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
