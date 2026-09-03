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
        Schema::table('document_signature_fields', function (Blueprint $table) {
            $table->longText('signature_image')->nullable()->after('height');
            $table->timestamp('signed_at')->nullable()->after('signature_image');
        });
    }
};
