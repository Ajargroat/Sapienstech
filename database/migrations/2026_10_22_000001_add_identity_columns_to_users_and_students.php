<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کد ملی and موبایل as first-class identities, email optional.
 *
 * A NULL email is legal under the existing (tenant_id, email) unique index
 * on MySQL and SQLite alike — no index change is needed for optional email.
 * `phone` is unique per tenant (mirrors email); `national_code` is unique
 * per table, with the across-table half enforced by the app-level
 * IranianIdentity::assertAvailable() check (docs/identity.md).
 *
 * Column guards keep this re-runnable on the live DB.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['users', 'students'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'national_code')) {
                    $blueprint->string('national_code', 10)->nullable()->after('email');
                }

                if (! Schema::hasColumn($table, 'phone')) {
                    $blueprint->string('phone', 20)->nullable()->after('national_code');
                }
            });

            if (! Schema::hasIndex($table, $table.'_national_code_unique')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique('national_code'));
            }

            if (! Schema::hasIndex($table, $table.'_tenant_id_phone_unique')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unique(['tenant_id', 'phone']));
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('email')->nullable()->change());
        }

        if (Schema::hasTable('student_join_requests')
            && ! Schema::hasColumn('student_join_requests', 'national_code')) {
            Schema::table(
                'student_join_requests',
                fn (Blueprint $blueprint) => $blueprint->string('national_code', 10)->nullable()->after('email'),
            );
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropUnique($table.'_tenant_id_phone_unique');
                $blueprint->dropUnique($table.'_national_code_unique');
                $blueprint->dropColumn(['phone', 'national_code']);
            });

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string('email')->nullable(false)->change());
        }

        if (Schema::hasTable('student_join_requests') && Schema::hasColumn('student_join_requests', 'national_code')) {
            Schema::table(
                'student_join_requests',
                fn (Blueprint $blueprint) => $blueprint->dropColumn('national_code'),
            );
        }
    }
};
