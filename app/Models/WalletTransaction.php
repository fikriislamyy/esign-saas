<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'organization_id',

        'type',

        'source_currency',
        'source_amount',
        'exchange_rate',

        'amount_usd_cents',

        'balance_before_usd_cents',
        'balance_after_usd_cents',

        'reference_type',
        'reference_id',

        'description',

        'created_by',

        'metadata',
    ];

    protected $casts = [
        'source_amount' => 'decimal:8',
        'exchange_rate' => 'decimal:12',

        'amount_usd_cents' => 'integer',

        'balance_before_usd_cents' => 'integer',
        'balance_after_usd_cents' => 'integer',

        'metadata' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(
            Wallet::class,
            'wallet_id'
        );
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class,
            'organization_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Related model that caused the transaction.
     *
     * Examples:
     * - WalletTopup
     * - DocumentSigner
     * - Document
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
