<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile fields for the settings hub.
 *
 * `avatar` mirrors students.avatar. `preferences` is the per-user layer for
 * things that must never leak to other visitors — most importantly the
 * "just for me" site layer applied by ApplyPersonalTheme, plus future
 * personal toggles. Both users and students get them so the student portal
 * can join the same machinery without a later migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('role');
            $table->json('preferences')->nullable()->after('avatar');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'preferences']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
