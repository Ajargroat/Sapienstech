<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $t) {
            if (! Schema::hasColumn('questions', 'supabase_id')) {
                // provenance + idempotent re-runs
                $t->string('supabase_id', 36)->nullable()->unique()->after('tenant_id');
            }
        });

        // Imported questions aren't tied to a book chapter yet.
        DB::statement('ALTER TABLE questions MODIFY chapter_id bigint UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn('supabase_id'));
        DB::statement('ALTER TABLE questions MODIFY chapter_id bigint UNSIGNED NOT NULL');
    }
};
