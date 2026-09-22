<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attachments on the two halves of an assignment: the teacher's brief
 * (a worksheet PDF or a photo of the task) on `assignments`, and the file
 * the student hands in on the `assignment_students` pivot. Both nullable:
 * an assignment without an attachment — and a submission that is only a
 * note — stays valid, so the rows created before this column existed are
 * untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assignments', 'file_path')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->string('file_path')->nullable()->after('description')
                    ->comment('Relative path under the tenant asset tree');
            });
        }

        if (! Schema::hasColumn('assignment_students', 'file_path')) {
            Schema::table('assignment_students', function (Blueprint $table) {
                $table->string('file_path')->nullable()->after('note')
                    ->comment('The student submission file');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assignments', 'file_path')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->dropColumn('file_path');
            });
        }

        if (Schema::hasColumn('assignment_students', 'file_path')) {
            Schema::table('assignment_students', function (Blueprint $table) {
                $table->dropColumn('file_path');
            });
        }
    }
};
