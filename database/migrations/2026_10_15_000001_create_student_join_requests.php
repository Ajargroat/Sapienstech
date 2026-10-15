<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student join requests («درخواست عضویت») and soft roster removal.
 *
 * A visitor on a tenant's website fills in the landing page's join section and
 * declares the payment they made (amount + transfer reference) — the same
 * vocabulary the deal-renewal flow already uses, so the tenant verifies it the
 * same way. The request then sits in `pending` until a tenant admin/owner
 * approves it, at which point the imported data becomes a real `students` row
 * and the request is stamped with `student_id`.
 *
 * The imported data is deliberately NOT written into `students` before
 * approval: an unapproved visitor must never appear in the roster, in counts,
 * or in chat pickers. This table is the staging area.
 *
 * `students.removed_at` is the soft roster marker: a removed student stays in
 * the database (their deals, attempts and chat history survive) but is hidden
 * from every staff-facing list and can no longer log in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('bio');
            $table->index(['tenant_id', 'removed_at'], 'students_tenant_removed_index');
        });

        Schema::create('student_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Imported profile data — the same shape a students row carries, so
            // approval is a straight copy rather than a re-mapping.
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('grade', 50)->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('major', 50)->nullable();
            $table->string('bio', 500)->nullable();

            // The declared payment. `reference` is the bank-transfer reference
            // the applicant typed in; the tenant verifies it manually against
            // the merchant account, exactly like DealPayment.
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 8)->default('IRT');
            $table->string('reference', 120)->nullable();

            $table->string('status', 16)->default('pending'); // pending|approved|rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Set on approval: the student row this request became.
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();

            /**
             * Duplicate guard, active only while a request is open.
             *
             * A plain unique (tenant_id, email) would be wrong: it would block a
             * rejected applicant from ever re-applying, and block an approved
             * student from ever submitting again. So the guard rides on a
             * separate nullable column that the model fills with
             * "tenant|email" while pending and clears on review — NULLs do not
             * collide in a unique index, which makes this a partial index in
             * effect. Re-submitting therefore UPDATES the open row instead of
             * queueing a duplicate.
             */
            $table->string('open_key', 200)->nullable();

            $table->timestamps();

            $table->unique('open_key');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);

            // No composite (tenant_id, student_id) key, for the reason already
            // documented on student_deals: MySQL rejects SET NULL on a
            // multi-column FK unless every column is nullable, and tenant_id
            // must stay NOT NULL. The plain student_id key above already SETs
            // NULL, and tenant identity is enforced by BelongsToTenant plus the
            // service, which only ever resolves a student through the
            // tenant-scoped model.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_join_requests');

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_tenant_removed_index');
            $table->dropColumn('removed_at');
        });
    }
};
