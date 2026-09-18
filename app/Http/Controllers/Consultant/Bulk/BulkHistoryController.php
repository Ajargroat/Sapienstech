<?php

namespace App\Http\Controllers\Consultant\Bulk;

use App\Http\Controllers\Controller;
use App\Models\BulkAction;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
use App\Support\StudentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * «تاریخچه»: every bulk operation with what it did, and a one-click revert
 * that deletes exactly the rows that batch created (bulk_action_id).
 *
 * Revert refuses to run once any student has started an attempt of a
 * bulk-assigned exam: deleting those assignments would SET NULL the attempt's
 * assignment_id (schema FK) and silently corrupt the results history. The
 * consultant resolves the conflict per student instead.
 */
class BulkHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $actions = BulkAction::query()
            ->where('tenant_id', $user->tenant_id)
            ->when($user->tenant?->hierarchy_type && ! $user->isTenantOwner(),
                fn ($query) => $query->where('user_id', $user->id))
            ->with('user:id,name')
            ->latest()
            ->paginate(15);

        return view('consultant.bulk.history', [
            'activeBulk' => 'history',
            'actions' => $actions,
        ]);
    }

    public function revert(BulkAction $action): RedirectResponse
    {
        $user = request()->user();

        DB::transaction(function () use ($action, $user) {
            $action = BulkAction::query()->where('tenant_id', $user->tenant_id)
                ->lockForUpdate()->findOrFail($action->id);
            $restricted = $user->tenant?->hierarchy_type && ! $user->isTenantOwner();
            abort_if($restricted && (int) $action->user_id !== (int) $user->id, 403);
            abort_if($action->isReverted(), 422, 'این دسته پیش‌تر واگرد شده است.');

            $targets = match ($action->kind) {
                BulkAction::KIND_EXAM => StudentAssignedQuiz::query(),
                BulkAction::KIND_SCHEDULE => ScheduleItem::query(),
                default => null,
            };

            if ($targets) {
                $targets->where('tenant_id', $action->tenant_id)->where('bulk_action_id', $action->id);

                if ($restricted) {
                    // Read every target before applying the current roster: never silently revert a subset.
                    $studentIds = (clone $targets)->lockForUpdate()->pluck('student_id')->unique();
                    $accessible = StudentAccess::scope(Student::withoutGlobalScope('staff_access'), $user)
                        ->whereIn('students.id', $studentIds)->count();
                    abort_if($accessible !== $studentIds->count(), 403);
                }
            }

            if ($action->kind === BulkAction::KIND_EXAM) {
                $taken = StudentTestAttempt::withoutGlobalScopes()
                    ->whereHas('assignment', fn ($q) => $q->where('bulk_action_id', $action->id))
                    ->exists();

                abort_if($taken, 422, 'برخی دانش‌آموزان این آزمون را شروع کرده‌اند؛ واگرد دسته‌ای مجاز نیست.');
            }

            $targets?->delete();
            $action->update(['reverted_at' => now()]);
        });

        return back()->with('success', 'واگرد انجام شد.');
    }
}
