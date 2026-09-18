<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * order_id holds Pakasir's external order reference. Stripe payments are
     * tracked by stripe_invoice_id instead and have nothing to put here.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_payments ALTER COLUMN order_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE subscription_payments SET order_id = 'BACKFILL-'||id WHERE order_id IS NULL");

        DB::statement('ALTER TABLE subscription_payments ALTER COLUMN order_id SET NOT NULL');
    }
};
