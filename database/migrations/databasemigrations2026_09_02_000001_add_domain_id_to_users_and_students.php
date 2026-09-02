<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('domain_id')->nullable()->after('tenant_id')
                ->constrained('domains')->nullOnDelete();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('domain_id')->nullable()->after('tenant_id')
                ->constrained('domains')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_id');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('domain_id');
        });
    }
};
