<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
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
            | Transaction Type
            |--------------------------------------------------------------------------
            |
            | topup
            | signature
            | refund
            | adjustment
            |
            */

            $table->string('type', 30);

            /*
            |--------------------------------------------------------------------------
            | Original Payment Currency
            |--------------------------------------------------------------------------
            |
            | usd
            | idr
            |
            */

            $table->string('source_currency', 3);

            /*
            |--------------------------------------------------------------------------
            | Original Amount
            |--------------------------------------------------------------------------
            |
            | USD:
            |   cents
            |
            | IDR:
            |   whole rupiah
            |
            | Example:
            |
            | $10.00 USD = 1000
            | Rp160,000 IDR = 160000
            |
            */

            $table->decimal('source_amount');

            /*
            |--------------------------------------------------------------------------
            | Exchange Rate
            |--------------------------------------------------------------------------
            |
            | For USD:
            |   1
            |
            | For IDR:
            |   Example 16000
            |   meaning 1 USD = Rp16,000
            |
            */

            $table->decimal(
                'exchange_rate',
                18,
                8
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Actual Wallet Movement
            |--------------------------------------------------------------------------
            |
            | This is ALWAYS USD cents.
            |
            | Positive:
            |   Money added
            |
            | Negative:
            |   Money consumed
            |
            */

            $table->bigInteger('amount_usd_cents');

            /*
            |--------------------------------------------------------------------------
            | Wallet Balance Snapshot
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger(
                'balance_before_usd_cents'
            );

            $table->unsignedBigInteger(
                'balance_after_usd_cents'
            );

            /*
            |--------------------------------------------------------------------------
            | Reference
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | DocumentSigner
            | WalletTopup
            |
            */

            $table->string('reference_type')
                ->nullable();

            $table->string('reference_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $table->string('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | User
            |--------------------------------------------------------------------------
            */

            $table->uuid('created_by')
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
                'wallet_id',
                'created_at',
            ]);

            $table->index([
                'organization_id',
                'created_at',
            ]);

            $table->index([
                'type',
                'source_currency',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
