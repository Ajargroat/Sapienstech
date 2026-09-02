<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $t) {
            if (! Schema::hasColumn('questions', 'question_image_bbox')) {
                // {"x","y","w","h"} crop rect + {"pw","ph"} page size, in pixels
                $t->json('question_image_bbox')->nullable()->after('question_image_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('questions', fn (Blueprint $t) => $t->dropColumn('question_image_bbox'));
    }
};
