<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The picker popup filters the bank by lesson, company and chapter, so the
 * raw (OCR'd) Supabase metadata has to survive the import. Values are stored
 * already normalized by App\Support\QuestionBankMeta: `subject` holds one of
 * the five canonical Persian lessons, `corp` a canonical company name, and
 * `chapter_label` a display label like "فصل ۴ · دهم". Nulls simply mean the
 * source row carried nothing usable, and the UI drops those tags/filters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('subject', 100)->nullable()->index()->after('difficulty');
            $table->string('corp', 100)->nullable()->index()->after('subject');
            $table->string('chapter_label', 100)->nullable()->after('corp');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['subject', 'corp', 'chapter_label']);
        });
    }
};
