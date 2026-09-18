<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table) {
            $table->string('provider', 20)->default('stripe');
            $table->string('order_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table) {
            $table->dropColumn(['provider', 'order_id']);
        });
    }
};
