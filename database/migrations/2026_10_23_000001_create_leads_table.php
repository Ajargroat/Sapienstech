<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads captured by the corporate site's modal and footer forms.
 *
 * Rows are written by `corporate-site/api.php`, which opens its own PDO
 * connection instead of booting Laravel — so the column names and types here
 * are the contract that script relies on. Keep them in sync.
 *
 * Platform-level, not tenant-scoped: sapienstech.ir is the company site, not a
 * tenant, and one lead is never "owned" by another tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            return;
        }

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('source', 60)->default('modal');
            $table->timestamp('created_at')->useCurrent();

            // The only read pattern so far: newest first.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
