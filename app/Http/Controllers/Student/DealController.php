<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DealNotification;
use App\Models\StudentDeal;
use App\Support\DealService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's own renewal page: their periods, the continue/withdraw
 * decision window at the end of each deal, their payment receipts and the
 * reminder/decision/payment notifications that accumulate as the period ends.
 *
 * Everything is keyed off the authenticated student — never a client id — and
 * the BelongsToTenant scope keeps it inside the current tenant.
 */
class DealController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student');

        // Idempotent, bounded: makes sure a due deal always has a prompt, even
        // if the nightly scheduler has not run (e.g. local development).
        DealService::notifyDueForStudent($student);

        $deals = StudentDeal::query()
            ->where('student_id', $student->id)
            ->with([
                'consultant:id,name',
                'payments' => fn ($q) => $q->latest('id'),
            ])
            ->withCount(['notifications as unread_count' => fn ($q) => $q->whereNull('read_at')])
            ->orderByRaw('CASE WHEN renewed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('ends_on')
            ->paginate(10);

        $notifications = DealNotification::query()
            ->where('student_id', $student->id)
            ->with('deal:id,ends_on,amount,decision')
            ->orderByRaw('read_at is null desc')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return view('student.deals', [
            'student' => $student,
            'deals' => $deals,
            'notifications' => $notifications,
            'unread' => $notifications->whereNull('read_at')->count(),
            'windowDays' => DealService::WINDOW_DAYS,
        ]);
    }

    public function decide(Request $request, StudentDeal $deal)
    {
        $student = $request->user('student');
        $this->authorizeDeal($student, $deal);

        $data = $request->validate([
            'decision' => ['required', 'in:continue,withdraw'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DealService::decide($deal, $data['decision'], $data['decision_note'] ?? null);

        return back()->with('status', 'تصمیم شما ثبت شد.');
    }

    public function submitPayment(Request $request, StudentDeal $deal)
    {
        $student = $request->user('student');
        $this->authorizeDeal($student, $deal);

        $data = $request->validate([
            'reference' => ['required', 'string', 'min:4', 'max:120'],
        ]);

        DealService::submitPayment($deal, trim($data['reference']));

        return back()->with('status', 'رسید شما ثبت شد و در انتظار تأیید مجموعه است.');
    }

    public function readNotification(Request $request, DealNotification $notification)
    {
        $student = $request->user('student');
        abort_unless(
            tenant() && (int) $notification->tenant_id === (int) tenant()->id
            && (int) $notification->student_id === (int) $student->id,
            404
        );

        $notification->forceFill(['read_at' => now()])->save();

        return back();
    }

    public function readAll(Request $request)
    {
        $student = $request->user('student');

        DealNotification::query()
            ->where('student_id', $student->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'همهٔ اطلاعیه‌ها خوانده شد.');
    }

    /** Bindings resolve before the tenant middleware; verify ownership here. */
    private function authorizeDeal($student, StudentDeal $deal): void
    {
        abort_unless(
            tenant() && (int) $deal->tenant_id === (int) tenant()->id
            && (int) $deal->student_id === (int) $student->id,
            404
        );
    }
}
