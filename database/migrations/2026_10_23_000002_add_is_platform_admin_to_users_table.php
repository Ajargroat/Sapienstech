<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives an ordinary account the operations console WITHOUT changing its role.
 *
 * The PDF pipeline is our own machinery, but the person running it is usually
 * also a tenant's admin — and `users.role` holds exactly one value, so
 * "promote to platform_admin" would silently strip their tenant powers
 * (`isTenantAdmin()`, the consultant panel, the roster). A separate boolean
 * says the second thing independently: "this account may also use /ops".
 *
 * Additive and reversible: default false, nothing reads it until it is granted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'is_platform_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'is_platform_admin')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_platform_admin');
        });
    }
};
