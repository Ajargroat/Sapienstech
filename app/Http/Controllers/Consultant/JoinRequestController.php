<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentJoinRequest;
use App\Models\User;
use App\Support\StudentEnrollment;
use App\Support\TenantRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * «درخواستها» — the queue of visitors waiting to become students.
 *
 * A visitor imports their data on the tenant's website and declares the payment
 * they made; this page is where the tenant confirms it. Approving turns the
 * imported data into a real student (see App\Support\StudentEnrollment).
 *
 * The same class also carries the *other* way onto the roster — `create()` and
 * `store()` — for a person the admin types in himself, with no visitor and no
 * declared payment behind them. Both write through App\Support\StudentEnrollment,
 * so the two paths share one set of semantics; `removeStudent()` was already a
 * roster action living here, for the same reason.
 *
 * Who may decide: tenant admin/owner only, matching the existing rule for
 * verifying a deal payment. Staff can reach the page and see what is waiting,
 * but the approve/reject/create buttons are admin-only, and the endpoints
 * enforce that server-side rather than trusting the hidden buttons.
 */
class JoinRequestController extends Controller
{
    /** The review queue's tabs. */
    public const STATUS_FILTERS = ['pending', 'approved', 'rejected', 'all'];

    /**
     * The hand-entry form's choice lists — fixed on purpose: the pickers must
     * offer choices on a fresh roster too, where no student values exist yet
     * to derive options from (a roster-fed variant broke on exactly that).
     *
     * @var list<string>
     */
    private const GRADE_CHOICES = ['دهم', 'یازدهم', 'دوازدهم'];

    /** @var list<string> */
    private const GENDER_CHOICES = ['پسر', 'دختر'];

    /** @var list<string> */
    private const MAJOR_CHOICES = ['ریاضی', 'تجربی', 'انسانی'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = (string) $request->query('status', StudentJoinRequest::STATUS_PENDING);

        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = StudentJoinRequest::STATUS_PENDING;
        }

        $query = StudentJoinRequest::query()
            ->with(['reviewer:id,name', 'student:id,tenant_id,name'])
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($matches) use ($q) {
                $matches->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        // Counts for the tabs, always tenant-wide and independent of the active
        // filter, so the numbers do not move as the reviewer pages through.
        $counts = [
            'pending' => StudentJoinRequest::query()->pending()->count(),
            'approved' => StudentJoinRequest::query()->where('status', StudentJoinRequest::STATUS_APPROVED)->count(),
            'rejected' => StudentJoinRequest::query()->where('status', StudentJoinRequest::STATUS_REJECTED)->count(),
        ];
        $counts['all'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];

        return view('consultant.requests', [
            'requests' => $query->paginate(15)->withQueryString(),
            'counts' => $counts,
            'filters' => ['status' => $status, 'q' => (string) $request->query('q', '')],
            'canManage' => $user->isTenantAdmin(),
        ]);
    }

    /**
     * One request in full, so the reviewer can check the declared payment
     * against the imported data before deciding.
     */
    public function show(StudentJoinRequest $joinRequest): View
    {
        // Bindings resolve before the tenant middleware, so the owning tenant is
        // verified here (the same guard the deal endpoints use).
        abort_unless(tenant() && (int) $joinRequest->tenant_id === (int) tenant()->id, 404);

        return view('consultant.students.request', [
            'request' => $joinRequest->load(['reviewer:id,name', 'student:id,tenant_id,name,email']),
            'canManage' => request()->user()->isTenantAdmin(),
        ]);
    }

    /**
     * Approve: the imported data becomes a student.
     *
     * The generated password is flashed to the session exactly once — it is
     * never stored in plain text, so this redirect is the only chance to read
     * it. The view shows it with a copy affordance and says plainly that it
     * cannot be shown again.
     */
    public function approve(Request $request, StudentJoinRequest $joinRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() && (int) $joinRequest->tenant_id === (int) tenant()->id, 404);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = StudentEnrollment::approve($joinRequest, $user, $data['review_note'] ?? null);
        } catch (RuntimeException $e) {
            // Already decided — most likely a double-click or a second admin.
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()
            ->route('consultant.requests.show', $joinRequest)
            ->with('status', 'درخواست تأیید شد و دانش‌آموز به فهرست اضافه شد.')
            ->with('generated_password', $result['password'])
            ->with('generated_password_for', $result['student']->email);
    }

    public function reject(Request $request, StudentJoinRequest $joinRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() && (int) $joinRequest->tenant_id === (int) tenant()->id, 404);

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            StudentEnrollment::reject($joinRequest, $user, $data['review_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()
            ->route('consultant.requests.index')
            ->with('status', 'درخواست رد شد.');
    }

    /**
     * The hand-entry form: the admin types the student's data himself.
     *
     * Deliberately *not* the public form's copy — there is no visitor and no
     * declared payment here, so the form collects the roster's own columns and
     * nothing else (no payment fields, no schema change; the user asked for
     * that explicitly). The optional consultant select exists so the created
     * student is visible to the consultant he is assigned to, not only to the
     * owner: in a hierarchical consultancy a `consultant_staff` sees only
     * `student_staff`-linked students (App\Support\StudentAccess).
     *
     * Grade/gender/major are pickers with fixed choice lists (the *_CHOICES
     * constants): the admin picks a value instead of typing it. Fixed on
     * purpose — the pickers must work on a fresh roster, where there are no
     * student values yet to derive choices from.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);

        return view('consultant.students.create', [
            'consultants' => $this->consultantsFor(),
            // Choice, not typing — and not derived from the roster either:
            // a fresh roster has no values to derive from, so the choices
            // are fixed lists (see the *_CHOICES constants).
            'gradeOptions' => collect(self::GRADE_CHOICES),
            'genderOptions' => collect(self::GENDER_CHOICES),
            'majorOptions' => collect(self::MAJOR_CHOICES),
        ]);
    }

    /**
     * Store an admin-typed student.
     *
     * The generated password is flashed exactly once, like approval's — it is
     * never stored in plain text, so the redirect is the only chance to read
     * it. The redirect target is the new student's profile rather than back to
     * the form, so the admin sees the person he just added.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() !== null, 404);

        $consultants = $this->consultantsFor();
        $offersConsultant = $consultants->isNotEmpty();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // The remaining columns are the roster's own; the max lengths are
            // the column widths (grade/gender/major 50, bio 500).
            'grade' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'major' => ['nullable', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'consultant_id' => [
                'nullable',
                'integer',
                // Scoped to this tenant AND this tenant's consultant role, so
                // a foreign tenant's user id (or a teacher's) is rejected
                // rather than silently written into the pivot.
                Rule::exists('users', 'id')
                    ->where('tenant_id', tenant()->id)
                    ->where('role', User::ROLE_CONSULTANT_STAFF),
            ],
        ]);

        $email = mb_strtolower(trim($data['email']));

        // Only an *active* roster row blocks: a removed row is reused, so an
        // email that exists-but-removed is legal. Same predicate as submit().
        $active = Student::withoutGlobalScopes()
            ->where('tenant_id', tenant()->id)
            ->where('email', $email)
            ->whereNull('removed_at')
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'email' => 'این ایمیل قبلاً در فهرست دانش‌آموزان این مجموعه ثبت شده است.',
            ]);
        }

        // Only honour a consultant id when the tenant is actually offered the
        // select, so a hand-crafted POST cannot link a student on a tenant
        // that has no consultant layer.
        $consultant = ($offersConsultant && ! empty($data['consultant_id']))
            ? $consultants->firstWhere('id', (int) $data['consultant_id'])
            : null;

        $result = StudentEnrollment::create(tenant(), [
            'name' => $data['name'],
            'email' => $email,
            'grade' => $data['grade'] ?? null,
            'gender' => $data['gender'] ?? null,
            'major' => $data['major'] ?? null,
            'bio' => $data['bio'] ?? null,
        ], $consultant);

        return redirect()
            ->route('consultant.student.profile', $result['student'])
            ->with('status', "«{$result['student']->name}» به فهرست دانش‌آموزان اضافه شد.")
            ->with('generated_password', $result['password'])
            ->with('generated_password_for', $result['student']->email);
    }

    /**
     * The tenant's consultants, for the optional assignment select.
     *
     * Empty for a non-hierarchical tenant (the admin *is* the one consultant)
     * and for a school (whose equivalent grouping is a classroom, deliberately
     * out of this plan's scope) — so the select simply does not render there.
     */
    private function consultantsFor(): Collection
    {
        $tenant = tenant();

        if (! $tenant || ! $tenant->hierarchy_type || TenantRoster::isSchool($tenant)) {
            return collect();
        }

        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_CONSULTANT_STAFF)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Remove a student from the roster, from their profile page.
     *
     * Soft by design: the row and its history survive (deals, attempts, chat),
     * the person disappears from every staff-facing list, and they can no longer
     * log in. There is deliberately no restore screen — removal is presented to
     * the consultant as final, and the user asked for it that way.
     */
    public function removeStudent(Request $request, Student $student): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTenantAdmin(), 403);
        abort_unless(tenant() && (int) $student->tenant_id === (int) tenant()->id, 404);

        // The confirmation the UI asks for is enforced here too: a destructive
        // action must not be one stray POST away.
        $request->validate([
            'confirm' => ['required', 'in:remove'],
        ]);

        StudentEnrollment::remove($student);

        return redirect()
            ->route('consultant.dashboard')
            ->with('status', "«{$student->name}» از فهرست دانش‌آموزان حذف شد.");
    }
}
