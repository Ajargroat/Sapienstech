<?php

namespace App\Http\Controllers\Consultant\Bulk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\Bulk\BulkExamAssignRequest;
use App\Models\BulkAction;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\Test;
use App\Support\BulkSelection;
use App\Support\StudentFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * «آزمون گروهی»: assign one existing test to many students at once, by
 * checkbox selection or by re-applying a filter set server-side.
 *
 * Mirrors the single-student assignment row shape from StudentExamController
 * (status scheduled, assigned_by stamped from the session, never from input).
 * Rows already assigned to the same test are skipped rather than duplicated,
 * and the whole batch is recorded as a BulkAction so تاریخچه can revert it.
 */
class BulkExamController extends Controller
{
    public function create(Request $request): View
    {
        $filters = StudentFilter::fromRequest($request);

        $query = Student::query();
        StudentFilter::apply($query, $filters);

        return view('consultant.bulk.exams', [
            'activeBulk' => 'exams',
            'tests' => Test::query()->latest()->get(['id', 'test_title', 'lesson', 'exam_type']),
            'students' => $query->get(['id', 'name', 'grade', 'gender', 'major']),
            'filters' => $filters,
            'gradeOptions' => StudentFilter::distinctOptions('grade'),
            'genderOptions' => StudentFilter::distinctOptions('gender'),
            'majorOptions' => StudentFilter::distinctOptions('major'),
        ]);
    }

    public function store(BulkExamAssignRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Tenant-scoped re-read; the exists rule already checked it, this
        // just hands us the model for the summary.
        $test = Test::query()->find((int) $data['test_id']);

        $ids = BulkSelection::resolve($request);

        if ($ids === []) {
            return back()->withInput()
                ->withErrors(['students' => 'هیچ دانش‌آموز معتبری انتخاب نشده است.']);
        }

        $alreadyAssigned = StudentAssignedQuiz::query()
            ->where('test_id', $test->id)
            ->whereIn('student_id', $ids)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $new = array_values(array_diff($ids, $alreadyAssigned));

        $action = BulkAction::create([
            'user_id' => $request->user()->id,
            'kind' => BulkAction::KIND_EXAM,
            'summary' => [
                'test_id' => $test->id,
                'test_title' => $test->test_title,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'requested' => count($ids),
                'skipped' => count($alreadyAssigned),
                'select_all' => $request->boolean('select_all'),
                'filters' => StudentFilter::fromRequest($request),
            ],
        ]);

        if ($new !== []) {
            $now = now();
            $tenantId = tenant()->id;
            $userId = $request->user()->id;
            $scheduledAt = isset($data['scheduled_at'])
                ? \Illuminate\Support\Carbon::parse($data['scheduled_at'])
                : null;

            $rows = array_map(static fn (int $studentId) => [
                'tenant_id' => $tenantId,
                'test_id' => $test->id,
                'student_id' => $studentId,
                'assigned_by_user_id' => $userId,
                'assigned_at' => $now,
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
                'is_completed' => 0,
                'bulk_action_id' => $action->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $new);

            DB::transaction(function () use ($rows, $action) {
                // Chunked insert: a tenant-wide select_all can be thousands
                // of rows and a single statement would blow past max_allowed_packet.
                foreach (array_chunk($rows, 500) as $chunk) {
                    StudentAssignedQuiz::insert($chunk);
                }

                $action->update(['affected_count' => count($rows)]);
            });
        } else {
            $action->update(['affected_count' => 0]);
        }

        return redirect()
            ->route('consultant.bulk.history')
            ->with('success', sprintf(
                '%d دانش‌آموز به آزمون «%s» اضافه شدند%s.',
                count($new),
                $test->test_title,
                $alreadyAssigned !== [] ? ' ('.count($alreadyAssigned).' از قبل واگذار شده بودند)' : ''
            ));
    }
}
