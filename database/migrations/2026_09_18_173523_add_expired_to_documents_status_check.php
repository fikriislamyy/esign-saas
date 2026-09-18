<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_status_check');

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check
             CHECK (status::text = ANY (ARRAY['draft','sent','completed','cancelled','expired']::text[]))"
        );
    }

    public function down(): void
    {
        DB::statement("UPDATE documents SET status = 'cancelled' WHERE status = 'expired'");

        DB::statement('ALTER TABLE documents DROP CONSTRAINT documents_status_check');

        DB::statement(
            "ALTER TABLE documents ADD CONSTRAINT documents_status_check
             CHECK (status::text = ANY (ARRAY['draft','sent','completed','cancelled']::text[]))"
        );
    }
};
