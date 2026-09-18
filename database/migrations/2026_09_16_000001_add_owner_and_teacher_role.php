<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Teachers are sub-levels of the tenant. Rather than denormalizing
         | teacher ids onto the tenants table (an un-enforceable 1NF breach),
         | the identity lives where it already does:
         |
         |   - tenants.owner_user_id  -> the ONE main admin of the tenant
         |   - users.role='teacher'   -> teachers, tenant-scoped by tenant_id
         |
         | which keeps referential integrity (ON DELETE rules), reuses the
         | existing domain-pinned login rules (canLoginThrough), and gives
         | every query a simple where('role', 'teacher') path.
         */
        Schema::table('tenants', function (Blueprint $t) {
            if (! Schema::hasColumn('tenants', 'owner_user_id')) {
                $t->unsignedBigInteger('owner_user_id')
                    ->nullable()
                    ->after('status')
                    ->comment('The tenant main admin (users.id)');
                $t->foreign('owner_user_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // MODIFY is a no-op when already applied, so this is safe on the live DB.
            DB::statement(
                "ALTER TABLE `users` MODIFY `role` ENUM('platform_admin','tenant_admin','consultant_staff','teacher') NOT NULL DEFAULT 'consultant_staff'"
            );
        }

        // Backfill: the tenant's oldest tenant_admin becomes the main admin.
        $admins = DB::table('users')
            ->where('role', 'tenant_admin')
            ->orderBy('tenant_id')
            ->orderBy('id')
            ->get(['tenant_id', 'id']);

        foreach ($admins->unique('tenant_id') as $admin) {
            DB::table('tenants')
                ->where('id', $admin->tenant_id)
                ->whereNull('owner_user_id')
                ->update(['owner_user_id' => $admin->id]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            if (Schema::hasColumn('tenants', 'owner_user_id')) {
                $t->dropForeign(['owner_user_id']);
                $t->dropColumn('owner_user_id');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            // Only roles that were creatable before this migration may remain.
            DB::table('users')->where('role', 'teacher')->update(['role' => 'consultant_staff']);
            DB::statement(
                "ALTER TABLE `users` MODIFY `role` ENUM('platform_admin','tenant_admin','consultant_staff') NOT NULL DEFAULT 'consultant_staff'"
            );
        }
    }
};
