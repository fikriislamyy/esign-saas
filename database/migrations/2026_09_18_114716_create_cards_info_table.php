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
        Schema::create('cards_info', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('card_brand', 20)->nullable();
            $table->string('card_last_four', 4);
            $table->unsignedSmallInteger('card_exp_month');
            $table->unsignedSmallInteger('card_exp_year');

            $table->string('stripe_payment_method_id')->unique();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards_info');
    }
};
