<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Organization
            |--------------------------------------------------------------------------
            |
            | One organization = one wallet.
            |
            */

            $table->foreignUuid('organization_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Canonical Wallet Balance
            |--------------------------------------------------------------------------
            |
            | The wallet is always stored internally in USD cents.
            |
            | Example:
            |
            | $10.00 = 1000
            |
            */

            $table->unsignedBigInteger('balance_usd_cents')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
