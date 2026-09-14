<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'owner_id',
        'logo',
    ];

    protected static function booted(): void
    {
        static::creating(function ($organization) {
            $organization->slug = Str::slug(
                $organization->name
            );
        });

        static::updating(function ($organization) {
            if ($organization->isDirty('name')) {
                $organization->slug = Str::slug(
                    $organization->name
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner()
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function documents()
    {
        return $this->hasMany(
            Document::class
        );
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(
            Wallet::class
        );
    }

    public function getWalletBalanceUsdCentsAttribute(): int
    {
        return (int) ($this->wallet?->balance_usd_cents ?? 0);
    }
}
