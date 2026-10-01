<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The question bank becomes a SHARED pool across tenants.
 *
 * Both concours tenants (tenant-one, salam) read one bank, so a pooled row
 * carries `tenant_id = NULL` and App\Models\Concerns\SharedAcrossTenants
 * scopes Question/Answer to "NULL or mine" instead of the hard tenant filter
 * BelongsToTenant applies everywhere else.
 *
 * Existing questions/answers rows are all artifacts of the single OCR import
 * (there is no consultant create-question UI), so today's tenant-stamped bank
 * *is* the pool — they are re-stamped to NULL here and every tenant gains read
 * access. test_questions/attempt_answers stay tenant-stamped and untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL-only, matching the house idiom for relaxing a NOT NULL column
        // (see 2026_09_02_130742): SQLite has no MODIFY and rebuilds through
        // change() below instead.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY tenant_id bigint UNSIGNED NULL');
            DB::statement('ALTER TABLE answers MODIFY tenant_id bigint UNSIGNED NULL');
        } else {
            if (Schema::hasColumn('questions', 'tenant_id')) {
                Schema::table('questions', fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->change());
            }
            if (Schema::hasColumn('answers', 'tenant_id')) {
                Schema::table('answers', fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable()->change());
            }
        }

        // Everything already in the bank becomes part of the shared pool.
        DB::table('questions')->whereNotNull('tenant_id')->update(['tenant_id' => null]);
        DB::table('answers')->whereNotNull('tenant_id')->update(['tenant_id' => null]);
    }

    public function down(): void
    {
        DB::table('questions')->whereNull('tenant_id')->update(['tenant_id' => 1]);
        DB::table('answers')->whereNull('tenant_id')->update(['tenant_id' => 1]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE questions MODIFY tenant_id bigint UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE answers MODIFY tenant_id bigint UNSIGNED NOT NULL');
        } else {
            Schema::table('questions', fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable(false)->change());
            Schema::table('answers', fn (Blueprint $t) => $t->unsignedBigInteger('tenant_id')->nullable(false)->change());
        }
    }
};
