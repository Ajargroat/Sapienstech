<?php

namespace App\Support;

use App\Models\DealNotification;
use App\Models\DealPayment;
use App\Models\Student;
use App\Models\StudentDeal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The renewal state machine for student deals.
 *
 * Invariants (all enforced transactionally here, never in the UI alone):
 * - A decision (continue/withdraw) is only allowed once the period is within
 *   WINDOW_DAYS of its end (or already overdue) and has not been renewed.
 * - A payment can only be submitted on a CONTINUE decision; its amount is
 *   always copied from the deal — client input never sets it.
 * - Only the tenant owner verifies payments. Approving one atomically marks
 *   the payment paid, stamps renewed_at and opens the next period with the
 *   same terms; the unique previous_deal_id index + renewal guards make a
 *   double extension impossible.
 * - Withdrawing cancels pending payments but never touches roster or access
 *   links: the paid-for period stays usable until ends_on.
 */
class DealService
{
    /** Days before ends_on when the student can decide and pay. */
    public const WINDOW_DAYS = 5;

    /** Sentinel day bucket for one-shot notification kinds (unique index). */
    private const ONE_SHOT_DAY = '1970-01-01';

    public static function windowOpen(StudentDeal $deal, ?Carbon $today = null): bool
    {
        $today ??= Carbon::today();

        if ($deal->renewed_at !== null) {
            return false;
        }

        return $today->greaterThanOrEqualTo($deal->ends_on->copy()->subDays(self::WINDOW_DAYS));
    }

    /** The student (or the owner acting for them) picks continue or withdraw. */
    public static function decide(StudentDeal $deal, string $decision, ?string $note = null): StudentDeal
    {
        if (! in_array($decision, [StudentDeal::DECISION_CONTINUE, StudentDeal::DECISION_WITHDRAW], true)) {
            throw ValidationException::withMessages(['decision' => 'تصمیم نامعتبر است.']);
        }

        return DB::transaction(function () use ($deal, $decision, $note) {
            $deal = StudentDeal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            self::assertDecidable($deal);

            if ($decision === StudentDeal::DECISION_WITHDRAW) {
                // Withdraw also cancels anything still awaiting verification.
                DealPayment::query()
                    ->where('deal_id', $deal->id)
                    ->where('status', DealPayment::STATUS_PENDING)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (DealPayment $payment) {
                        $payment->forceFill([
                            'status' => DealPayment::STATUS_CANCELLED,
                            'reviewed_at' => now(),
                            'review_note' => 'لغو شد: دانش‌آموز از ادامهٔ دوره منصرف شد.',
                        ])->save();
                    });
            }

            $deal->decision = $decision;
            $deal->decision_note = $note !== null && $note !== '' ? $note : null;
            $deal->decided_at = now();
            $deal->save();

            DealNotification::updateOrCreate(
                ['deal_id' => $deal->id, 'kind' => DealNotification::KIND_DECISION, 'day' => self::ONE_SHOT_DAY],
                [
                    'tenant_id' => $deal->tenant_id,
                    'student_id' => $deal->student_id,
                    'message' => $decision === StudentDeal::DECISION_CONTINUE
                        ? 'تصمیم شما ثبت شد: ادامهٔ دوره. برای تمدید، رسید پرداخت را ارسال کنید تا بررسی شود.'
                        : 'تصمیم شما ثبت شد: انصراف از ادامهٔ دوره. دسترسی شما تا پایان دورهٔ فعلی برقرار می‌ماند.',
                    'read_at' => null,
                ]
            );

            return $deal;
        });
    }

    /**
     * The tenant's built-in simulated gateway. Same guards as the real
     * (bank-transfer) path, but the receipt is issued and auto-approved in
     * one step — the period renews immediately, no owner verification.
     * A stand-in until the server-side gateway lands; swap the body for the
     * real PSP callback flow when it does.
     */
    public static function payOnline(StudentDeal $deal): DealPayment
    {
        return DB::transaction(function () use ($deal) {
            $deal = StudentDeal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            self::assertDecidable($deal);

            if ($deal->decision !== StudentDeal::DECISION_CONTINUE) {
                throw ValidationException::withMessages([
                    'payment' => 'برای پرداخت ابتدا باید ادامهٔ دوره را انتخاب کنید.',
                ]);
            }

            $pending = DealPayment::query()
                ->where('deal_id', $deal->id)
                ->where('status', DealPayment::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if ($pending) {
                throw ValidationException::withMessages([
                    'payment' => 'شما یک رسید در انتظار بررسی دارید؛ لطفاً منتظر تأیید بمانید.',
                ]);
            }

            $payment = DealPayment::create([
                'tenant_id' => $deal->tenant_id,
                'deal_id' => $deal->id,
                'student_id' => $deal->student_id,
                'amount' => $deal->amount,
                'currency' => $deal->currency,
                'reference' => sprintf('SIM-%s-%06d', now()->format('Ymd'), random_int(0, 999999)),
                'status' => DealPayment::STATUS_PENDING,
            ]);

            self::reviewPayment($payment, DealPayment::STATUS_PAID, 'درگاه آزمایشی (شبیه‌سازی)');

            return $payment;
        });
    }

    /** Student submits the bank-transfer reference; amount is copied from the deal. */
    public static function submitPayment(StudentDeal $deal, string $reference): DealPayment
    {
        return DB::transaction(function () use ($deal, $reference) {
            $deal = StudentDeal::query()->whereKey($deal->id)->lockForUpdate()->firstOrFail();
            self::assertDecidable($deal);

            if ($deal->decision !== StudentDeal::DECISION_CONTINUE) {
                throw ValidationException::withMessages([
                    'reference' => 'برای ثبت پرداخت ابتدا باید ادامهٔ دوره را انتخاب کنید.',
                ]);
            }

            $pending = DealPayment::query()
                ->where('deal_id', $deal->id)
                ->where('status', DealPayment::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if ($pending) {
                throw ValidationException::withMessages([
                    'reference' => 'شما یک رسید در انتظار بررسی دارید؛ لطفاً منتظر تأیید بمانید.',
                ]);
            }

            $duplicate = DealPayment::query()
                ->where('tenant_id', $deal->tenant_id)
                ->where('reference', $reference)
                ->first();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'reference' => 'این شمارهٔ پیگیری قبلاً ثبت شده است.',
                ]);
            }

            return DealPayment::create([
                'tenant_id' => $deal->tenant_id,
                'deal_id' => $deal->id,
                'student_id' => $deal->student_id,
                'amount' => $deal->amount,
                'currency' => $deal->currency,
                'reference' => $reference,
                'status' => DealPayment::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Owner verification. Rejecting only closes the receipt; approving also
     * renews: paid + renewed_at + the next deal in one transaction.
     * `$reviewer` is null for system-side approvals (the simulated gateway).
     *
     * @return StudentDeal|null the opened next period when approved, else null
     */
    public static function reviewPayment(DealPayment $payment, string $status, ?string $note, ?User $reviewer = null): ?StudentDeal
    {
        if (! in_array($status, [DealPayment::STATUS_PAID, DealPayment::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages(['status' => 'وضعیت بررسی نامعتبر است.']);
        }

        return DB::transaction(function () use ($payment, $status, $note, $reviewer) {
            $payment = DealPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== DealPayment::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'این پرداخت قبلاً بررسی شده است.']);
            }

            $deal = StudentDeal::query()->whereKey($payment->deal_id)->lockForUpdate()->firstOrFail();

            if ($status === DealPayment::STATUS_REJECTED) {
                $payment->forceFill([
                    'status' => DealPayment::STATUS_REJECTED,
                    'reviewed_by' => $reviewer?->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ])->save();

                self::notifyPayment($deal, 'رسید شما تأیید نشد. لطفاً وضعیت پرداخت خود را بررسی و در صورت نیاز رسید دیگری ارسال کنید.');

                return null;
            }

            if ($deal->decision !== StudentDeal::DECISION_CONTINUE) {
                throw ValidationException::withMessages(['status' => 'دانش‌آموز ادامهٔ دوره را انتخاب نکرده است.']);
            }

            if ($deal->renewed_at !== null) {
                throw ValidationException::withMessages(['status' => 'این دوره قبلاً تمدید شده است.']);
            }

            $today = Carbon::today();
            $nextStart = $deal->ends_on->greaterThan($today)
                ? $deal->ends_on->copy()->addDay()
                : $today->copy();
            $nextEnd = $nextStart->copy()->addDays(max(1, (int) $deal->period_days) - 1);

            $payment->forceFill([
                'status' => DealPayment::STATUS_PAID,
                'reviewed_by' => $reviewer?->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ])->save();

            $deal->forceFill(['renewed_at' => now()])->save();

            $next = StudentDeal::create([
                'tenant_id' => $deal->tenant_id,
                'student_id' => $deal->student_id,
                'consultant_id' => $deal->consultant_id,
                'previous_deal_id' => $deal->id,
                'starts_on' => $nextStart->toDateString(),
                'ends_on' => $nextEnd->toDateString(),
                'amount' => $deal->amount,
                'currency' => $deal->currency,
                'period_days' => $deal->period_days,
                'decision' => StudentDeal::DECISION_PENDING,
                'created_by' => $reviewer?->id,
            ]);

            self::notifyPayment($deal, 'پرداخت شما تأیید شد و دوره تمدید گردید.');

            return $next;
        });
    }

    /**
     * Opens the first period for a student. One open (unrenewed) deal per
     * student at a time — reopening after a withdrawal is done by editing the
     * decision, not by stacking deals.
     */
    public static function createDeal(
        Student $student,
        ?User $consultant,
        string $startsOn,
        string $endsOn,
        int $amount,
        ?User $creator = null,
    ): StudentDeal {
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->startOfDay();

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['ends_on' => 'پایان دوره باید بعد از شروع آن باشد.']);
        }

        if ($end->lessThan(Carbon::today())) {
            throw ValidationException::withMessages(['ends_on' => 'ثبت دورهٔ گذشته مجاز نیست.']);
        }

        if ($consultant !== null
            && (int) $consultant->tenant_id !== (int) $student->tenant_id) {
            throw ValidationException::withMessages(['consultant_id' => 'مشاور انتخاب‌شده به این مجموعه تعلق ندارد.']);
        }

        return DB::transaction(function () use ($student, $consultant, $start, $end, $amount, $creator) {
            $open = StudentDeal::query()
                ->where('student_id', $student->id)
                ->whereNull('renewed_at')
                ->lockForUpdate()
                ->first();

            if ($open !== null) {
                throw ValidationException::withMessages(['student_id' => 'برای این دانش‌آموز یک دورهٔ فعال ثبت شده است.']);
            }

            return StudentDeal::create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'consultant_id' => $consultant?->id,
                'previous_deal_id' => null,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'amount' => $amount,
                'period_days' => (int) $start->diffInDays($end) + 1,
                'decision' => StudentDeal::DECISION_PENDING,
                'created_by' => $creator?->id,
            ]);
        });
    }

    /**
     * One reminder per deal per day; returns the notification or null when a
     * reminder for today already exists (or the deal is withdrawn/renewed).
     */
    public static function remind(StudentDeal $deal): ?DealNotification
    {
        if ($deal->renewed_at !== null || $deal->decision === StudentDeal::DECISION_WITHDRAW) {
            return null;
        }

        $today = Carbon::today()->toDateString();
        $existing = DealNotification::query()
            ->where('deal_id', $deal->id)
            ->where('kind', DealNotification::KIND_REMINDER)
            ->where('day', $today)
            ->first();

        if ($existing !== null) {
            return null;
        }

        return DealNotification::create([
            'tenant_id' => $deal->tenant_id,
            'deal_id' => $deal->id,
            'student_id' => $deal->student_id,
            'kind' => DealNotification::KIND_REMINDER,
            'day' => $today,
            'message' => sprintf(
                '%s عزیز، دورهٔ مشاورهٔ شما در %s به پایان می‌رسد. لطفاً دربارهٔ ادامه یا انصراف خود تصمیم بگیرید.',
                $deal->student->name ?? 'دانش‌آموز',
                persian_digits($deal->ends_on->format('Y/m/d'))
            ),
        ]);
    }

    /** Bounded, idempotent prompt used when a student opens the deals page. */
    public static function notifyDueForStudent(Student $student): int
    {
        return self::remindWindowDeals(
            StudentDeal::query()->where('student_id', $student->id)
        );
    }

    /**
     * Daily scheduler across every non-suspended tenant: reminders 5 days
     * before the end, on the due day and on every overdue day (unique index
     * dedupes per day). Withdrawn students are never re-nagged.
     */
    public static function remindDue(): int
    {
        $count = 0;

        $tenantIds = Tenant::query()
            ->where('status', '!=', 'suspended')
            ->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $query = StudentDeal::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('renewed_at')
                ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
                ->whereDate('ends_on', '<=', Carbon::today()->copy()->addDays(self::WINDOW_DAYS));

            foreach ($query->cursor() as $deal) {
                if (self::remind($deal) !== null) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private static function remindWindowDeals($query): int
    {
        $count = 0;

        $deals = $query
            ->whereNull('renewed_at')
            ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
            ->whereDate('ends_on', '<=', Carbon::today()->copy()->addDays(self::WINDOW_DAYS))
            ->with('student')
            ->cursor();

        foreach ($deals as $deal) {
            if (self::remind($deal) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private static function notifyPayment(StudentDeal $deal, string $message): void
    {
        DealNotification::updateOrCreate(
            ['deal_id' => $deal->id, 'kind' => DealNotification::KIND_PAYMENT, 'day' => self::ONE_SHOT_DAY],
            [
                'tenant_id' => $deal->tenant_id,
                'student_id' => $deal->student_id,
                'message' => $message,
                'read_at' => null,
            ]
        );
    }

    private static function assertDecidable(StudentDeal $deal): void
    {
        if ($deal->renewed_at !== null) {
            throw ValidationException::withMessages(['decision' => 'این دوره تمدید شده است.']);
        }

        if (! self::windowOpen($deal)) {
            throw ValidationException::withMessages([
                'decision' => 'زمان تصمیم‌گیری نزدیک به پایان دوره هنوز فرا نرسیده است.',
            ]);
        }
    }
}
