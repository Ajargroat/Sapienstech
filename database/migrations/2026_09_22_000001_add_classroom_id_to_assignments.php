<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An assignment targets a grade; it may now narrow that to one classroom of
 * the grade. Nullable: null keeps the previous grade-wide behaviour, so the
 * rows created before this column existed stay valid as-is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assignments', 'classroom_id')) {
            return;
        }

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('grade')
                ->constrained('classrooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assignments', 'classroom_id')) {
            return;
        }

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
        });
    }
};
