<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deal renewal ("تمدید و پرداخت"): every student sits inside dated periods
 * (deals). Near the end of a period the student decides — continue or
 * withdraw — and, when continuing, submits the bank-transfer reference of
 * their payment. The tenant owner verifies the deposit against the bank and
 * approving it closes the period and opens the next one.
 *
 * Tenant identity is enforced at the schema level with a composite
 * (tenant_id, id) foreign key on students — same pattern as the hierarchy
 * pivots — so a deal row can never silently cross tenants, even via
 * direct SQL. The unique index on previous_deal_id makes a renewal chain
 * strictly linear: one period can be extended exactly once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('previous_deal_id')->nullable()->constrained('student_deals')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            // Integer tomans; client input never sets this (copied from the deal).
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('IRT');
            $table->unsignedInteger('period_days');
            // pending → continue | withdraw; decided_at stamps the student's call.
            $table->string('decision', 16)->default('pending');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('previous_deal_id');
            $table->index(['tenant_id', 'student_id']);
            $table->index('ends_on');
            $table->index(['tenant_id', 'consultant_id']);

            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])
                ->on('students')->cascadeOnDelete();
            // No composite (tenant_id, consultant_id) key: MySQL rejects
            // SET NULL on a multi-column FK unless every column is nullable,
            // and tenant_id must stay NOT NULL. The plain consultant_id key
            // above already SETs NULL, and tenant scoping for the consultant
            // is enforced in DealService/controllers.
        });

        Schema::create('deal_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('student_deals')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // The student's bank-transfer reference; the owner verifies it
            // manually against the merchant account. No gateway, no secrets.
            $table->string('reference', 120);
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('IRT');
            $table->string('status', 16)->default('pending'); // pending|paid|rejected|cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // A reference can never be reused inside a tenant (double-spend guard).
            $table->unique(['tenant_id', 'reference']);
            $table->index(['deal_id', 'status']);

            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])
                ->on('students')->cascadeOnDelete();
        });

        Schema::create('deal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('student_deals')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32); // reminder | decision | payment
            $table->text('message');
            // reminder dedupe day; one-shot kinds use a sentinel so the unique
            // index below keeps exactly one row per (deal, kind) for them.
            $table->date('day')->default('1970-01-01');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['deal_id', 'kind', 'day']);
            $table->index(['tenant_id', 'student_id', 'read_at']);

            $table->foreign(['tenant_id', 'student_id'])->references(['tenant_id', 'id'])
                ->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_notifications');
        Schema::dropIfExists('deal_payments');
        Schema::dropIfExists('student_deals');
    }
};
