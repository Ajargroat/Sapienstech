<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultation requests («درخواست مشاوره») — one-way public submissions.
 *
 * A visitor on a tenant's website asks to be contacted back. Unlike a join
 * request there is no payment to declare and no roster seat behind it, so the
 * row carries no reference and no reviewer columns: approving or rejecting
 * only flips `status`, and the row itself is the record of truth.
 *
 * The pending re-submission dedupe is a plain query on (tenant, pending,
 * lower(email)) — no `open_key` column, because a request's life here is one
 * decision; the same visitor may always submit again as a fresh row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Who asked to be contacted and how to reach them. Email is the
            // dedupe key while the request is pending; phone is optional.
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message');

            // pending|approved|rejected — the same three-step vocabulary as
            // student_join_requests. Kept as a plain string column (the
            // in-repo idiom, see create_student_join_requests) rather than a
            // MySQL ENUM, so the schema runs unchanged on sqlite :memory:.
            $table->string('status', 16)->default('pending');

            $table->timestamps();

            // The dedupe query rides this: tenant + pending + email.
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_requests');
    }
};
