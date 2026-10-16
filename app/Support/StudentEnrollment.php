<?php

namespace App\Support;

use App\Models\Student;
use App\Models\StudentJoinRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The one place a person becomes a student.
 *
 * Two entry points, one destination:
 *
 *   1. `submit()`  — a visitor imports their data on the tenant's website and
 *                    declares the payment they made. The request waits.
 *   2. `approve()` — a tenant admin/owner confirms the payment landed. This is
 *                    the moment the imported data is copied into `students`.
 *   3. `create()`  — a tenant admin types a student's data in himself, for a
 *                    person assigned outside the request flow (no visitor, no
 *                    declared payment). Same destination, same semantics: the
 *                    roster row, a hand-over password, the removed-row reuse.
 *
 * A record therefore reaches `students` only through this class, whether it was
 * imported from the public form or typed by the admin — the two paths cannot
 * drift apart, which is what keeps "how a person becomes a student" one rule.
 *
 * Everything runs inside a transaction with the request row locked, so two
 * admins clicking Approve at once cannot create two students from one request —
 * the second sees a non-pending status and stops.
 *
 * Tenant identity always comes from the resolved tenant (the request's host) or
 * from the request row itself — never from client input.
 */
final class StudentEnrollment
{
    /** Length of the generated hand-over password. */
    private const PASSWORD_LENGTH = 12;

    /**
     * Record a visitor's join request, or refresh the one already open for that
     * person on this tenant.
     *
     * Re-submitting updates the open row rather than queueing a duplicate: a
     * person who mistyped their transfer reference must be able to correct it
     * without leaving two pending requests for the consultant to puzzle over.
     * The open row is keyed on the primary identifier — کد ملی, else phone,
     * else email — through `open_key`.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException when the person already has an active roster
     *                             row on this tenant (any identity matches), or
     *                             the کد ملی already belongs to another account
     */
    public static function submit(Tenant $tenant, array $data): StudentJoinRequest
    {
        $email = self::normalizeEmail($data['email'] ?? null);
        $phone = IranianIdentity::normalizePhone($data['phone'] ?? null);
        $nationalCode = IranianIdentity::normalizeNationalCode($data['national_code'] ?? null);

        /**
         * An active roster match on ANY identity is refused outright.
         *
         * Approval issues a fresh password for the matched person, so accepting
         * the request would hand whoever typed it a working login to an
         * existing student's account — an account takeover, not a duplicate
         * row. A removed student (`removed_at` set) is deliberately NOT
         * blocked: coming back after removal is exactly what re-applying is
         * for.
         */
        if (self::activeRosterExists($tenant->id, $phone, $nationalCode, $email)) {
            throw ValidationException::withMessages([
                'phone' => 'این مشخصات قبلاً در این مجموعه ثبت شده است. برای ورود از صفحهٔ ورود استفاده کنید.',
            ]);
        }

        // A removed roster row must not free the کد ملی for *another* account
        // (one کد ملی = one account platform-wide); it stays a reuse target for
        // this tenant's own re-approval, which asserts with an ignore id.
        IranianIdentity::assertAvailable($nationalCode, includeRemoved: false);

        $identity = $nationalCode !== null
            ? 'nc:'.$nationalCode
            : ($phone !== null ? 'ph:'.$phone : 'em:'.(string) $email);
        $openKey = StudentJoinRequest::openKey($tenant->id, $identity);

        return DB::transaction(function () use ($tenant, $data, $email, $phone, $nationalCode, $openKey) {
            $existing = StudentJoinRequest::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('open_key', $openKey)
                ->where('status', StudentJoinRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'tenant_id' => $tenant->id,
                'name' => trim((string) $data['name']),
                'email' => $email,
                'phone' => $phone,
                'national_code' => $nationalCode,
                'grade' => self::nullable($data['grade'] ?? null),
                'gender' => self::nullable($data['gender'] ?? null),
                'major' => self::nullable($data['major'] ?? null),
                'bio' => self::nullable($data['bio'] ?? null),
                'plan_slug' => self::nullable($data['plan_slug'] ?? null),
                'plan_name' => self::nullable($data['plan_name'] ?? null),
                'amount' => max(0, (int) ($data['amount'] ?? 0)),
                'currency' => (string) ($data['currency'] ?? 'IRT'),
                'reference' => self::nullable($data['reference'] ?? null),
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return StudentJoinRequest::withoutGlobalScopes()->create($attributes + [
                'status' => StudentJoinRequest::STATUS_PENDING,
                'open_key' => $openKey,
            ]);
        });
    }

    /**
     * Approve a request: the imported data becomes a student.
     *
     * Returns the plain-text password **once** so the consultant can hand it to
     * the student. It is never stored in plain text and never recoverable
     * afterwards — only the hash lands in `students.password`.
     *
     * @return array{student: Student, password: string}
     */
    public static function approve(StudentJoinRequest $request, User $reviewer, ?string $note = null): array
    {
        return DB::transaction(function () use ($request, $reviewer, $note) {
            $locked = StudentJoinRequest::withoutGlobalScopes()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('این درخواست قبلاً بررسی شده است.');
            }

            $password = self::generatePassword();

            // The same person may have been a student before — approved once,
            // then removed, or applying again after a rejection. Reuse that row
            // instead of inserting, because `students` carries unique
            // (tenant_id, email) and (tenant_id, phone) keys and a blind insert
            // would crash the approval with a duplicate-key error the admin
            // cannot act on. Reusing also means "re-added" students get their
            // old deals, attempts and chat history back, which is the point of
            // a soft removal. The match is on any identity — a legacy row may
            // predate the phone/کد ملی columns.
            $student = self::findByIdentity($locked->tenant_id, $locked->phone, $locked->national_code, $locked->email);

            // An *active* match means the person is already on the roster (for
            // example, the admin added them by hand while the request waited):
            // approving would overwrite the live account's password — refuse
            // instead.
            if ($student && ! $student->isRemoved()) {
                throw ValidationException::withMessages([
                    'phone' => 'این شخص قبلاً در فهرست دانشآموزان این مجموعه ثبت شده است.',
                ]);
            }

            IranianIdentity::assertAvailable($locked->national_code, ignoreStudentId: $student?->id);

            $attributes = [
                'name' => $locked->name,
                'phone' => $locked->phone,
                'national_code' => $locked->national_code,
                'email' => $locked->email,
                'grade' => $locked->grade,
                'gender' => $locked->gender,
                'major' => $locked->major,
                'bio' => $locked->bio,
                'password' => Hash::make($password),
                // A fresh approval always returns the student to the roster,
                // which is what makes re-approval a valid way to undo a
                // mistaken removal.
                'removed_at' => null,
            ];

            if ($student) {
                $student->forceFill($attributes)->save();
            } else {
                $student = Student::withoutGlobalScopes()->create($attributes + [
                    'tenant_id' => $locked->tenant_id,
                ]);
            }

            $locked->update([
                'status' => StudentJoinRequest::STATUS_APPROVED,
                'student_id' => $student->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                // Release the duplicate guard: this email may apply again later.
                'open_key' => null,
            ]);

            return ['student' => $student, 'password' => $password];
        });
    }

    /**
     * Create a student directly from the admin's own form.
     *
     * The second sanctioned way onto the roster: a person assigned "out of the
     * blue", with no visitor and no declared payment behind them, so there is
     * no request row to copy from and no `amount`/`reference` to record. The
     * destination and the semantics are nonetheless `approve()`'s — the same
     * transaction, the same generated hand-over password, the same reuse of a
     * previously-removed row — because those are properties of `students`, not
     * of the request flow.
     *
     * Returns the plain-text password **once**: it is never stored in plain
     * text and never recoverable afterwards, only the hash lands in
     * `students.password`.
     *
     * When `$consultant` is given, the `student_staff` link is written in the
     * same transaction. In a hierarchical consultancy a `consultant_staff`
     * sees only linked students (see App\Support\StudentAccess), so without
     * the link the student the admin just added would be invisible to the very
     * consultant he was added for.
     *
     * @param  array<string, mixed>  $data
     * @return array{student: Student, password: string}
     */
    public static function create(Tenant $tenant, array $data, ?User $consultant = null): array
    {
        return DB::transaction(function () use ($tenant, $data, $consultant) {
            $email = self::normalizeEmail($data['email'] ?? null);
            $phone = IranianIdentity::normalizePhone($data['phone'] ?? null);
            $nationalCode = IranianIdentity::normalizeNationalCode($data['national_code'] ?? null);
            $password = self::generatePassword();

            // Same reuse rule as `approve()`: `students` carries unique
            // (tenant_id, email) and (tenant_id, phone) keys, so a person who
            // was a student before and was removed must have that row returned
            // to the roster rather than have a second one inserted. Reusing
            // also gives them their old deals, attempts and chat history back,
            // which is the point of a soft removal. The match is on any
            // identity — a legacy row may predate the phone/کد ملی columns.
            $student = self::findByIdentity($tenant->id, $phone, $nationalCode, $email);

            // An *active* match means the admin is adding someone already on
            // the roster — refuse rather than overwrite the live account.
            if ($student && ! $student->isRemoved()) {
                throw ValidationException::withMessages([
                    'phone' => 'این شخص قبلاً در فهرست دانشآموزان این مجموعه ثبت شده است.',
                ]);
            }

            IranianIdentity::assertAvailable($nationalCode, ignoreStudentId: $student?->id);

            $attributes = [
                'name' => trim((string) $data['name']),
                'phone' => $phone,
                'national_code' => $nationalCode,
                'email' => $email,
                'grade' => self::nullable($data['grade'] ?? null),
                'gender' => self::nullable($data['gender'] ?? null),
                'major' => self::nullable($data['major'] ?? null),
                'bio' => self::nullable($data['bio'] ?? null),
                'password' => Hash::make($password),
                // Creating always puts the person on the roster, which is what
                // makes this a valid way to undo a mistaken removal.
                'removed_at' => null,
            ];

            if ($student) {
                $student->forceFill($attributes)->save();
            } else {
                // Tenant identity comes from the resolved tenant argument,
                // never from the submitted data.
                $student = Student::withoutGlobalScopes()->create($attributes + [
                    'tenant_id' => $tenant->id,
                ]);
            }

            if ($consultant) {
                // Keyed on the pivot's composite primary key, so a re-created
                // student who was already linked to this consultant does not
                // conflict.
                DB::table('student_staff')->updateOrInsert(
                    [
                        'tenant_id' => $tenant->id,
                        'student_id' => $student->id,
                        'user_id' => $consultant->id,
                    ],
                );
            }

            return ['student' => $student, 'password' => $password];
        });
    }

    /** Reject a request. The imported data stays on the row for the record. */
    public static function reject(StudentJoinRequest $request, User $reviewer, ?string $note = null): StudentJoinRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $note) {
            $locked = StudentJoinRequest::withoutGlobalScopes()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('این درخواست قبلاً بررسی شده است.');
            }

            $locked->update([
                'status' => StudentJoinRequest::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
                // Released so a rejected applicant can re-apply once they have
                // actually paid.
                'open_key' => null,
            ]);

            return $locked;
        });
    }

    /**
     * Remove a student from the roster without deleting anything.
     *
     * The row survives — deals, attempts, chat history and report cards all
     * keep working — but the student disappears from every staff-facing list
     * and can no longer log in. This is deliberately the same operation on a
     * hierarchical and a non-hierarchical tenant: there is no per-consultant
     * `student_staff` link to detach on the latter, so a single soft marker is
     * what makes the action mean the same thing everywhere.
     */
    public static function remove(Student $student): Student
    {
        $student->forceFill(['removed_at' => now()])->save();

        return $student;
    }

    /**
     * Re-add a previously removed student to the roster.
     *
     * There is no restore button in the UI (removal is presented to the
     * consultant as final), but the underlying operation is a plain marker
     * clear so a future screen — or an operator with tinker — can undo a
     * mistaken removal without touching the database by hand.
     */
    public static function restore(Student $student): Student
    {
        $student->forceFill(['removed_at' => null])->save();

        return $student;
    }

    /** A random, hand-overable password. */
    private static function generatePassword(): string
    {
        return Str::password(self::PASSWORD_LENGTH, symbols: false);
    }

    /**
     * An active roster row matching ANY of the given identities.
     *
     * One predicate for both entry points: `submit()` uses it to refuse a
     * request outright, and it is what stops approval/creation from handing a
     * fresh password to an existing login.
     */
    public static function activeRosterExists(int $tenantId, ?string $phone, ?string $nationalCode, ?string $email): bool
    {
        if ($phone === null && $nationalCode === null && $email === null) {
            return false;
        }

        return Student::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('removed_at')
            ->where(function (Builder $query) use ($phone, $nationalCode, $email) {
                foreach (['phone' => $phone, 'national_code' => $nationalCode, 'email' => $email] as $column => $value) {
                    if ($value !== null) {
                        $query->orWhere($column, $value);
                    }
                }
            })
            ->exists();
    }

    /**
     * The roster row matching ANY of the identities, removed or not.
     *
     * `students` carries unique (tenant_id, email) and (tenant_id, phone)
     * keys, so a blind insert of a person seen before would crash with a
     * duplicate-key error; the reuse target is found by whichever identity the
     * old row carried.
     */
    private static function findByIdentity(int $tenantId, ?string $phone, ?string $nationalCode, ?string $email): ?Student
    {
        if ($phone === null && $nationalCode === null && $email === null) {
            return null;
        }

        return Student::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $query) use ($phone, $nationalCode, $email) {
                foreach (['phone' => $phone, 'national_code' => $nationalCode, 'email' => $email] as $column => $value) {
                    if ($value !== null) {
                        $query->orWhere($column, $value);
                    }
                }
            })
            ->first();
    }

    /** Trimmed lowercase email; empty means absent — email is optional now. */
    private static function normalizeEmail(mixed $value): ?string
    {
        $email = mb_strtolower(trim((string) $value));

        return $email === '' ? null : $email;
    }

    /** Trim a value, treating an empty string as absent. */
    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
