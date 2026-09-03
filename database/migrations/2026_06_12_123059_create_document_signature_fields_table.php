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
        Schema::create('document_signature_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('document_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('signer_id')
                ->constrained('document_signers')
                ->cascadeOnDelete();

            $table->integer('page');

            $table->double('x');
            $table->double('y');
            $table->double('width');
            $table->double('height');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signature_fields');
    }
};
