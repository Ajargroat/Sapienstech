<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\DealNotification;
use App\Models\DealPayment;
use App\Models\Student;
use App\Models\StudentDeal;
use App\Models\User;
use App\Support\DealService;
use App\Support\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tenant-side deal renewal manager: the due list, the decision/payment state
 * of every period, the owner's create/verify actions and the reminder trigger
 * (the demo-feature mock's consultant view, wired to the database).
 *
 * Roster scoping comes from the Student global scope (StudentAccess); write
 * actions — creating deals and verifying payments — are tenant-admin only.
 */
class DealController extends Controller
{
    public const STATUS_FILTERS = ['all', 'due', 'soon', 'continue', 'withdraw', 'pending', 'renewed'];

    private function applyFilter($query, string $status, Carbon $today): void
    {
        switch ($status) {
            case 'due':
                $query->whereNull('renewed_at')
                    ->whereDate('ends_on', '<', $today)
                    ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW);
                break;
            case 'soon':
                $query->whereNull('renewed_at')
                    ->whereDate('ends_on', '>=', $today)
                    ->whereDate('ends_on', '<=', $today->copy()->addDays(DealService::WINDOW_DAYS));
                break;
            case 'pending':
                $query->whereNull('renewed_at')
                    ->whereHas('payments', fn ($p) => $p->where('status', DealPayment::STATUS_PENDING));
                break;
            case 'renewed':
                $query->whereNotNull('renewed_at');
                break;
            case StudentDeal::DECISION_CONTINUE:
            case StudentDeal::DECISION_WITHDRAW:
                $query->where('decision', $status)->whereNull('renewed_at');
                break;
            case 'all':
            default:
                break;
        }
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $query = StudentDeal::query()
            ->with([
                'student:id,tenant_id,name,grade,major,email',
                'consultant:id,name',
                'payments' => fn ($q) => $q->latest('id'),
                'notifications' => fn ($q) => $q->latest('id')->limit(5),
            ]);

        $this->applyFilter($query, (string) $request->query('status', 'all'), $today);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$q}%"));
        }

        if ($consultantId = (int) $request->query('consultant_id', 0)) {
            $query->where('consultant_id', $consultantId);
        }

        $deals = $query
            ->orderByRaw('CASE WHEN renewed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('ends_on')
            ->paginate(15)
            ->withQueryString();

        // Summary always reflects the tenant-wide view, independent of filters.
        $open = StudentDeal::query()->whereNull('renewed_at');

        $summary = [
            'total' => (clone $open)->count(),
            'due' => (clone $open)
                ->whereDate('ends_on', '<', $today)
                ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
                ->count(),
            'pending' => DealPayment::query()->where('status', DealPayment::STATUS_PENDING)->count(),
            'collected' => (int) DealPayment::query()->where('status', DealPayment::STATUS_PAID)->sum('amount'),
        ];

        $students = $user->isTenantAdmin()
            ? Student::query()->orderBy('name')->get(['id', 'name', 'grade'])
            : collect();

        $consultants = User::query()
            ->whereIn('role', [User::ROLE_CONSULTANT_STAFF, User::ROLE_TENANT_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $pendingPayments = $user->isTenantAdmin()
            ? DealPayment::query()
                ->where('status', DealPayment::STATUS_PENDING)
                ->with(['deal:id,tenant_id,student_id,ends_on', 'deal.student:id,tenant_id,name'])
                ->orderBy('id')
                ->get()
            : collect();

        // The demo's «سررسیدهای تمدید» view: every open deal in (or past) the
        // decision window, most overdue first, across the whole roster.
        $dueDeals = StudentDeal::query()
            ->whereNull('renewed_at')
            ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
            ->whereDate('ends_on', '<=', $today->copy()->addDays(DealService::WINDOW_DAYS))
            ->with(['student:id,tenant_id,name,grade,major', 'consultant:id,name'])
            ->orderBy('ends_on')
            ->limit(30)
            ->get();

        // Sent reminders (demo's «پیام‌های ارسالی»): what was nudged, to whom,
        // when — and whether the student has seen it yet.
        $reminders = DealNotification::query()
            ->where('kind', DealNotification::KIND_REMINDER)
            ->with(['student:id,tenant_id,name', 'deal:id,ends_on'])
            ->latest('id')
            ->limit(40)
            ->get();

        // Demo's consultant cards: one row per consultant with their open-deal
        // rollup; each row drills into the filtered list below.
        $consultantGroups = $consultants->map(function (User $consultant) use ($today) {
            $open = StudentDeal::query()
                ->where('consultant_id', $consultant->id)
                ->whereNull('renewed_at');

            $deals = (clone $open)
                ->with(['student:id,tenant_id,name,grade,major', 'payments' => fn ($q) => $q->latest('id')])
                ->orderBy('ends_on')
                ->get();

            $withdrawn = $deals->where('decision', StudentDeal::DECISION_WITHDRAW);
            $active = $deals->where('decision', '!=', StudentDeal::DECISION_WITHDRAW);
            $due = $active->filter(fn ($deal) => $deal->daysLeft($today) <= 0);
            $soon = $active->filter(fn ($deal) => ($left = $deal->daysLeft($today)) > 0 && $left <= DealService::WINDOW_DAYS);

            return [
                'consultant' => $consultant,
                'deals' => $deals,
                'total' => $deals->count(),
                'due' => $due->count(),
                'soon' => $soon->count(),
                'ok' => $active->count() - $due->count() - $soon->count(),
                'withdrawn' => $withdrawn->count(),
                'revenue' => (int) $active->sum('amount'),
            ];
        })->filter(fn ($group) => $group['total'] > 0)->values();

        // Bell dropdown: pending receipts (owner) plus deals already overdue.
        $overdueDeals = StudentDeal::query()
            ->whereNull('renewed_at')
            ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
            ->whereDate('ends_on', '<', $today)
            ->with(['student:id,tenant_id,name', 'consultant:id,name'])
            ->orderBy('ends_on')
            ->limit(12)
            ->get();

        return view('consultant.deals', [
            'deals' => $deals,
            'summary' => $summary,
            'pendingPayments' => $pendingPayments,
            'dueDeals' => $dueDeals,
            'reminders' => $reminders,
            'consultantGroups' => $consultantGroups,
            'notifCount' => $pendingPayments->count() + $overdueDeals->count(),
            'overdueDeals' => $overdueDeals,
            'students' => $students,
            'consultants' => $consultants,
            'canManage' => $user->isTenantAdmin(),
            'filters' => [
                'status' => (string) $request->query('status', 'all'),
                'q' => (string) $request->query('q', ''),
                'consultant_id' => (int) $request->query('consultant_id', 0),
            ],
            'windowDays' => DealService::WINDOW_DAYS,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'consultant_id' => ['nullable', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
        ]);

        // Tenant scope + roster scope resolve both at controller time; the
        // explicit checks make a foreign id fail closed even before that.
        $student = Student::query()->findOrFail($data['student_id']);
        abort_unless(tenant() && (int) $student->tenant_id === (int) tenant()->id, 404);
        abort_unless(StudentAccess::allows($user, $student), 403);

        $consultant = null;
        if (! empty($data['consultant_id'])) {
            $consultant = User::query()
                ->where('tenant_id', $student->tenant_id)
                ->whereIn('role', [User::ROLE_CONSULTANT_STAFF, User::ROLE_TENANT_ADMIN])
                ->findOrFail($data['consultant_id']);
        }

        DealService::createDeal(
            $student,
            $consultant,
            $data['starts_on'],
            $data['ends_on'],
            (int) $data['amount'],
            $user,
        );

        return back()->with('status', 'دوره ثبت شد و دانش‌آموز در پایان آن برای تصمیم مطلع می‌شود.');
    }

    public function remind(Request $request, StudentDeal $deal)
    {
        $user = $request->user();
        // Route bindings resolve before the tenant middleware (SubstituteBindings
        // runs earlier in the web group), so the owning tenant is verified here.
        abort_unless(tenant() && (int) $deal->tenant_id === (int) tenant()->id, 404);

        $allowed = $user->isTenantAdmin()
            || ((int) $deal->consultant_id === (int) $user->id && StudentAccess::allows($user, $deal->student));

        abort_unless($allowed, 403);

        DealService::remind($deal->load('student'));

        return back()->with('status', 'یادآوری برای دانش‌آموز ثبت شد.');
    }

    public function reviewPayment(Request $request, DealPayment $payment)
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        // Bindings resolve before tenant middleware — verify ownership here.
        abort_unless(tenant() && (int) $payment->tenant_id === (int) tenant()->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.DealPayment::STATUS_PAID.','.DealPayment::STATUS_REJECTED],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $next = DealService::reviewPayment(
            $payment,
            $data['status'],
            $data['review_note'] ?? null,
            $user,
        );

        return back()->with('status', $next
            ? 'پرداخت تأیید شد و دورهٔ جدید آغاز گردید.'
            : 'پرداخت رد شد.');
    }

    /**
     * The bell's notification center: everything deal-related that needs the
     * tenant's attention — receipts awaiting verification (owner only), deals
     * in/past the decision window, and the sent-reminder log.
     */
    public function notifications(Request $request): View
    {
        $user = $request->user();
        $today = Carbon::today();

        $pendingPayments = $user->isTenantAdmin()
            ? DealPayment::query()
                ->where('status', DealPayment::STATUS_PENDING)
                ->with(['deal:id,tenant_id,student_id,ends_on', 'deal.student:id,tenant_id,name'])
                ->orderBy('id')
                ->get()
            : collect();

        $dueDeals = StudentDeal::query()
            ->whereNull('renewed_at')
            ->where('decision', '!=', StudentDeal::DECISION_WITHDRAW)
            ->whereDate('ends_on', '<=', $today->copy()->addDays(DealService::WINDOW_DAYS))
            ->with(['student:id,tenant_id,name,grade,major', 'consultant:id,name'])
            ->orderBy('ends_on')
            ->paginate(20);

        $reminders = DealNotification::query()
            ->where('kind', DealNotification::KIND_REMINDER)
            ->with(['student:id,tenant_id,name', 'deal:id,ends_on'])
            ->latest('id')
            ->limit(40)
            ->get();

        return view('consultant.notifications', [
            'pendingPayments' => $pendingPayments,
            'dueDeals' => $dueDeals,
            'reminders' => $reminders,
            'canManage' => $user->isTenantAdmin(),
        ]);
    }
}
