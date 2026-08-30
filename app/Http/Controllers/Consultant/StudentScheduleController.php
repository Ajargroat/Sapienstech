<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\StoreScheduleItemRequest;
use App\Http\Requests\Consultant\UpdateScheduleItemRequest;
use App\Models\ItemComment;
use App\Models\ScheduleItem;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Consultant-facing weekly schedule editor for a single student.
 *
 * Behavioral reference: consultant_schedule_editor.php / consultant_schedule_script.js
 * (weekly grid, drag-to-create, 15-minute snapping, Saturday-first Persian
 * week). Persistence reference: the existing `schedule_items` /
 * `item_comments` tables -- no new tables are introduced.
 *
 * Tenant isolation / IDOR protection:
 * - {student} is implicitly bound to Student, whose BelongsToTenant global
 *   scope already restricts the query to the current tenant (the same
 *   pattern StudentFeatureController::profile() uses). assertStudentBelongsToTenant()
 *   re-asserts this explicitly so a missing/failed tenant context fails
 *   closed (404) instead of silently matching.
 * - {item} is implicitly bound to ScheduleItem, which carries the same
 *   BelongsToTenant scope, so an item id from another tenant will not
 *   resolve at all (404) before this controller ever runs.
 * - assertItemBelongsToStudent() additionally checks item->student_id
 *   against the {student} in the URL, so a consultant cannot reach a
 *   *different* (but same-tenant) student's item by pairing a student id
 *   they legitimately manage with an item id they found/guessed elsewhere.
 * - Every mutation re-derives start/end datetimes server-side from
 *   week_start_date + day_index + start_time/end_time (see
 *   resolveDatetimes()) rather than trusting client-computed datetime
 *   strings, and tenant_id / student_id are never accepted from request
 *   input.
 */
class StudentScheduleController extends Controller
{
    public function edit(Student $student): View
    {
        $this->assertStudentBelongsToTenant($student);

        return view('consultant.students.schedule', [
            'student' => $student,
        ]);
    }

    public function items(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);

        $weekStart = $this->resolveWeekStart($request->query('week_start_date'));
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $items = ScheduleItem::query()
            ->where('student_id', $student->id)
            ->whereBetween('start_datetime', [$weekStart, $weekEnd])
            ->orderBy('start_datetime')
            ->get();

        return response()->json([
            'week_start_date' => $weekStart->toDateString(),
            'events' => $items->map(fn (ScheduleItem $item) => $this->toEventArray($item))->values(),
        ]);
    }

    public function store(StoreScheduleItemRequest $request, Student $student): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);

        [$start, $end, $weekStart] = $this->resolveDatetimes($request->validated());

        $item = new ScheduleItem($request->safe()->only([
            'title', 'description', 'book_name', 'page_count', 'test_count', 'link_url',
        ]));
        $item->student_id = $student->id;
        $item->week_start_date = $weekStart->toDateString();
        $item->start_datetime = $start;
        $item->end_datetime = $end;
        $item->color = $request->input('color') ?: '#3b82f6';
        $item->item_type = 'consultant_event';
        $item->created_by_type = 'user';
$item->created_by_user_id = null;        $item->save();

        return response()->json(['success' => true, 'event' => $this->toEventArray($item)], 201);
    }

    public function update(UpdateScheduleItemRequest $request, Student $student, ScheduleItem $item): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);
        $this->assertItemBelongsToStudent($item, $student);

        abort_if(
            $item->item_type !== 'consultant_event',
            403,
            'این مورد توسط دانش‌آموز ثبت شده و قابل ویرایش توسط مشاور نیست.'
        );

        [$start, $end, $weekStart] = $this->resolveDatetimes($request->validated());

        $item->fill($request->safe()->only([
            'title', 'description', 'book_name', 'page_count', 'test_count', 'link_url',
        ]));
        $item->color = $request->input('color') ?: $item->color;
        $item->week_start_date = $weekStart->toDateString();
        $item->start_datetime = $start;
        $item->end_datetime = $end;
        $item->save();

        return response()->json(['success' => true, 'event' => $this->toEventArray($item)]);
    }

    public function destroy(Student $student, ScheduleItem $item): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);
        $this->assertItemBelongsToStudent($item, $student);

        abort_if(
            $item->item_type !== 'consultant_event',
            403,
            'این مورد توسط دانش‌آموز ثبت شده و قابل حذف توسط مشاور نیست.'
        );

        $item->delete();

        return response()->json(['success' => true]);
    }

    public function comments(Student $student, ScheduleItem $item): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);
        $this->assertItemBelongsToStudent($item, $student);

        $comments = $item->comments()->get()->map(fn (ItemComment $c) => [
            'username' => $c->commenterName(),
            'comment_text' => $c->comment_text,
            'created_at' => optional($c->created_at)->format('Y-m-d H:i'),
        ])->values();

        return response()->json($comments);
    }

    private function assertStudentBelongsToTenant(Student $student): void
    {
        $tenant = tenant();

        abort_unless(
            $tenant && (int) $student->tenant_id === (int) $tenant->id,
            404
        );
    }

    private function assertItemBelongsToStudent(ScheduleItem $item, Student $student): void
    {
        abort_unless((int) $item->student_id === (int) $student->id, 404);
    }

    private function resolveWeekStart(?string $requested): Carbon
    {
        $base = null;

        if ($requested && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested)) {
            try {
                $base = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
            } catch (Throwable) {
                $base = null;
            }
        }

        $base ??= Carbon::today();

        // Saturday-anchored (Persian) week. Carbon::dayOfWeek is 0=Sun..6=Sat,
        // so this mirrors the reference JS's `(jsDay + 1) % 7` day mapping.
        $offset = ($base->dayOfWeek + 1) % 7;

        return $base->copy()->subDays($offset)->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon} [start_datetime, end_datetime, week_start]
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

    private function toEventArray(ScheduleItem $item): array
    {
        return [
            'item_id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'start_datetime' => $item->start_datetime->format('Y-m-d H:i:s'),
            'end_datetime' => $item->end_datetime->format('Y-m-d H:i:s'),
            'color' => $item->color,
            'item_type' => $item->item_type,
            'link_url' => $item->link_url,
            'book_name' => $item->book_name,
            'test_count' => $item->test_count,
            'page_count' => $item->page_count,
            'is_completed' => (bool) $item->is_completed,
            'completion_timestamp' => optional($item->completion_timestamp)->format('Y-m-d H:i:s'),
        ];
    }
}
