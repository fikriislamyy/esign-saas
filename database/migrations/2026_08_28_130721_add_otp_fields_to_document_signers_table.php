<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->string('otp_hash')->nullable()->after('token');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_hash');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_attempts');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn([
                'otp_hash',
                'otp_expires_at',
                'otp_attempts',
                'otp_verified_at',
                'otp_last_sent_at',
            ]);
        });
    }
};
