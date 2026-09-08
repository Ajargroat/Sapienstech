<?php

namespace App\Http\Controllers\Consultant\Bulk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\Bulk\BulkScheduleAssignRequest;
use App\Models\BulkAction;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Support\BulkSelection;
use App\Support\StudentFilter;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * «برنامه گروهی»: apply one weekly block template to many students at once.
 *
 * Datetimes are derived server-side from week_start_date + day_index +
 * times, exactly like StudentScheduleController::resolveDatetimes — the
 * client never sends raw datetimes it computed. The Saturday-anchored week
 * normalization is duplicated here deliberately rather than extracted from
 * that controller, so the single-student editor stays untouched; if the two
 * ever drift, the schedule tests will say so.
 */
class BulkScheduleController extends Controller
{
    public function create(Request $request): View
    {
        $filters = StudentFilter::fromRequest($request);

        $query = Student::query();
        StudentFilter::apply($query, $filters);

        return view('consultant.bulk.schedule', [
            'activeBulk' => 'schedule',
            'students' => $query->get(['id', 'name', 'grade', 'gender', 'major']),
            'filters' => $filters,
            'gradeOptions' => StudentFilter::distinctOptions('grade'),
            'genderOptions' => StudentFilter::distinctOptions('gender'),
            'majorOptions' => StudentFilter::distinctOptions('major'),
            'weekStart' => $this->resolveWeekStart(Carbon::today()->toDateString())->toDateString(),
        ]);
    }

    public function store(BulkScheduleAssignRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ids = BulkSelection::resolve($request);

        if ($ids === []) {
            return back()->withInput()
                ->withErrors(['students' => 'هیچ دانش‌آموز معتبری انتخاب نشده است.']);
        }

        [$start, $end, $weekStart] = $this->resolveDatetimes($data);

        $action = BulkAction::create([
            'user_id' => $request->user()->id,
            'kind' => BulkAction::KIND_SCHEDULE,
            'summary' => [
                'title' => $data['title'],
                'day_index' => (int) $data['day_index'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'week_start_date' => $weekStart->toDateString(),
                'requested' => count($ids),
                'select_all' => $request->boolean('select_all'),
                'filters' => StudentFilter::fromRequest($request),
            ],
        ]);

        $now = now();
        $tenantId = tenant()->id;
        $userId = $request->user()->id;

        $rows = array_map(static fn (int $studentId) => [
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'week_start_date' => $weekStart->toDateString(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'color' => $data['color'] ?? '#3b82f6',
            'item_type' => 'consultant_event',
            'created_by_type' => 'user',
            'created_by_user_id' => $userId,
            'link_url' => $data['link_url'] ?? null,
            'book_name' => $data['book_name'] ?? null,
            'test_count' => $data['test_count'] ?? null,
            'page_count' => $data['page_count'] ?? null,
            'is_completed' => 0,
            'bulk_action_id' => $action->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $ids);

        DB::transaction(function () use ($rows, $action) {
            foreach (array_chunk($rows, 500) as $chunk) {
                ScheduleItem::insert($chunk);
            }

            $action->update(['affected_count' => count($rows)]);
        });

        return redirect()
            ->route('consultant.bulk.history')
            ->with('success', sprintf('بلوک «%s» برای %d دانش‌آموز ثبت شد.', $data['title'], count($ids)));
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon} [start, end, week_start]
     */
    private function resolveDatetimes(array $validated): array
    {
        $weekStart = $this->resolveWeekStart($validated['week_start_date']);
        $day = $weekStart->copy()->addDays((int) $validated['day_index']);

        $start = $day->copy()->setTimeFromTimeString($validated['start_time']);
        $end = $day->copy()->setTimeFromTimeString($validated['end_time']);

        abort_if($end->lessThanOrEqualTo($start), 422, 'ساعت پایان باید بعد از ساعت شروع باشد.');

        return [$start, $end, $weekStart];
    }

    private function resolveWeekStart(?string $requested): Carbon
    {
        $base = null;

        if ($requested && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            try {
                $base = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
            } catch (\Throwable) {
                $base = null;
            }
        }

        $base ??= Carbon::today();

        // Saturday-anchored (Persian) week. Carbon::dayOfWeek is 0=Sun..6=Sat.
        $offset = ($base->dayOfWeek + 1) % 7;

        return $base->copy()->subDays($offset)->startOfDay();
    }
}
