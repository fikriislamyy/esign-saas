<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'organization_id',
        'balance_usd_cents',
    ];

    protected $casts = [
        'balance_usd_cents' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            WalletTransaction::class
        );
    }

    public function topups(): HasMany
    {
        return $this->hasMany(
            WalletTopup::class
        );
    }
}
