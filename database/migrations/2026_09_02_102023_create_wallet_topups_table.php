<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Wallet / Organization
            |--------------------------------------------------------------------------
            */

            $table->foreignId('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Payment Currency
            |--------------------------------------------------------------------------
            */

            $table->string('currency', 3);

            /*
            |--------------------------------------------------------------------------
            | Amount Paid
            |--------------------------------------------------------------------------
            |
            | USD:
            |   cents
            |
            | IDR:
            |   whole rupiah
            |
            */

            $table->unsignedBigInteger('amount');

            /*
            |--------------------------------------------------------------------------
            | Exchange Rate Used For Conversion
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'exchange_rate',
                18,
                8
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Amount Credited To Wallet
            |--------------------------------------------------------------------------
            |
            | Always USD cents.
            |
            */

            $table->unsignedBigInteger(
                'wallet_amount_usd_cents'
            );

            /*
            |--------------------------------------------------------------------------
            | Stripe
            |--------------------------------------------------------------------------
            */

            $table->string(
                'stripe_checkout_session_id'
            )->nullable()->unique();

            $table->string(
                'stripe_payment_intent_id'
            )->nullable()->index();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->string(
                'status',
                30
            )->default('pending');

            /*
            |--------------------------------------------------------------------------
            | User
            |--------------------------------------------------------------------------
            */

            $table->uuid('created_by')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */

            $table->timestamp('paid_at')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */

            $table->json('metadata')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'organization_id',
                'created_at',
            ]);

            $table->index([
                'status',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topups');
    }
};
