<?php

namespace App\Http\Controllers\Consultant\Bulk;

use App\Http\Controllers\Controller;
use App\Models\BulkAction;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentTestAttempt;
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
        $actions = BulkAction::query()
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
        abort_if($action->isReverted(), 422, 'این دسته پیش‌تر واگرد شده است.');

        if ($action->kind === BulkAction::KIND_EXAM) {
            $taken = StudentTestAttempt::withoutGlobalScopes()
                ->whereHas('assignment', fn ($q) => $q->where('bulk_action_id', $action->id))
                ->exists();

            abort_if($taken, 422, 'برخی دانش‌آموزان این آزمون را شروع کرده‌اند؛ واگرد دسته‌ای مجاز نیست.');
        }

        DB::transaction(function () use ($action) {
            $deleted = match ($action->kind) {
                BulkAction::KIND_EXAM => StudentAssignedQuiz::query()
                    ->where('bulk_action_id', $action->id)->delete(),
                BulkAction::KIND_SCHEDULE => \App\Models\ScheduleItem::query()
                    ->where('bulk_action_id', $action->id)->delete(),
                default => 0,
            };

            $action->update(['reverted_at' => now()]);
        });

        return back()->with('success', 'واگرد انجام شد.');
    }
}
