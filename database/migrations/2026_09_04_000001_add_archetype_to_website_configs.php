<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * website_configs was built for this and never finished: it has theme/logo/
 * colour columns that no view reads, and an empty table. This adds the one
 * field the new resolver needs to let a tenant admin pick a visual identity
 * bundle without a deploy.
 *
 * Everything else a tenant edits lives in layout_config (JSON), which is
 * merged as the top-most layer by App\Support\SiteConfig.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_configs', function (Blueprint $table) {
            $table->string('archetype', 60)->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('website_configs', function (Blueprint $table) {
            $table->dropColumn('archetype');
        });
    }
};
