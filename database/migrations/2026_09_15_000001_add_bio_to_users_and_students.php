<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The profile hub's identity header shows a free-text description under the
 * name (the "bio" line on X). Both account tables get it so the consultant
 * and student portals render the same header structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'bio')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('bio', 500)->nullable()->after('avatar');
            });
        }

        if (! Schema::hasColumn('students', 'bio')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('bio', 500)->nullable()->after('avatar');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bio');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('bio');
        });
    }
};
