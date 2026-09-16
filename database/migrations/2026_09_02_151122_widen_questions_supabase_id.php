<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only widen; SQLite ignores varchar lengths and the base
        // schema already declares varchar(64).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY supabase_id varchar(64) NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE questions MODIFY supabase_id varchar(36) NULL');
    }
};
