<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('file_path');

            $table->unsignedBigInteger('file_size');

            $table->string('mime_type');

            $table->enum('status', [
                'draft',
                'sent',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
