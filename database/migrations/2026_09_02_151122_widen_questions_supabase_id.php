<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE questions MODIFY supabase_id varchar(64) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE questions MODIFY supabase_id varchar(36) NULL');
    }
};
