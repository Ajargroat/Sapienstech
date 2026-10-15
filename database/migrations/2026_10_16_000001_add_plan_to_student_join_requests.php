<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plan a join request was filed against.
 *
 * The landing page's plans section determines the prices («طرح‌ها»); the join
 * form lets the visitor name *which* plan they paid for, never an amount. This
 * column stores that choice so the consultant sees both sides of the
 * comparison on the review screen — what the plan costs, and what the visitor
 * declared.
 *
 * `plan_name` is denormalized on purpose: it is the plan's name at the moment
 * the request was filed, so a later rename or price change in the tenant's
 * config does not silently rewrite the history of an already-reviewed request.
 * Nullable, so every request filed before plans existed (or by a tenant that
 * has no plans) stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_join_requests', function (Blueprint $table) {
            $table->string('plan_slug', 40)->nullable()->after('bio');
            $table->string('plan_name', 255)->nullable()->after('plan_slug');
        });
    }

    public function down(): void
    {
        Schema::table('student_join_requests', function (Blueprint $table) {
            $table->dropColumn(['plan_slug', 'plan_name']);
        });
    }
};
