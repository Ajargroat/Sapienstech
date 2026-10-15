<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreScheduleItemCommentRequest;
use App\Models\ItemComment;
use App\Models\ScheduleItem;
use App\Models\ScheduleWeekNote;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The student half of the weekly schedule: reading the consultant's notes,
 * marking a block done, and saying something about it.
 *
 * Everything here is keyed off the authenticated *student guard* -- never a
 * client-supplied student id -- and re-checked against `student_id` on the
 * row before any write. On top of that the BelongsToTenant scope on
 * ScheduleItem / ScheduleWeekNote / ItemComment keeps every query inside the
 * current tenant, so a guessed id from another tenant does not resolve at all.
 *
 * `{item}` is bound by id (not by a nested relation) on purpose: the ownership
 * check has to answer 404 for someone else's block, and that check is the only
 * place that decision is made.
 */
class StudentSchedulePortalController extends Controller
{
    /** The consultant's notes for a week, one entry per weekday. */
    public function notes(Request $request): JsonResponse
    {
        $student = $request->user('student');
        $weekStart = $this->resolveWeekStart($request->query('week_start_date'));

        $stored = ScheduleWeekNote::query()
            ->where('student_id', $student->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->get()
            ->keyBy('day_index');

        $days = [];

        foreach (ScheduleWeekNote::DAYS as $index => $label) {
            $note = $stored->get($index);
            $text = $note?->note_text;

            $days[] = [
                'day_index' => $index,
                'label' => $label,
                'date' => $weekStart->copy()->addDays($index)->toDateString(),
                'text' => $text,
                'has_text' => is_string($text) && trim($text) !== '',
                'updated_at' => optional($note?->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return response()->json([
            'week_start_date' => $weekStart->toDateString(),
            'days' => $days,
            'note_count' => count(array_filter($days, fn (array $day) => $day['has_text'])),
        ]);
    }

    /** Flip a block between "done" and "not yet". */
    public function toggleCompletion(Request $request, ScheduleItem $item): JsonResponse
    {
        $student = $this->assertOwnedByStudent($request, $item);

        $data = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        $item->is_completed = (bool) $data['is_completed'];
        $item->completion_timestamp = $item->is_completed ? now() : null;
        $item->save();

        return response()->json([
            'success' => true,
            'item_id' => $item->id,
            'student_id' => $student->id,
            'is_completed' => (bool) $item->is_completed,
            'completion_timestamp' => optional($item->completion_timestamp)->format('Y-m-d H:i:s'),
        ]);
    }

    /** Leave a comment on a block (the "why I couldn't" channel). */
    public function storeComment(StoreScheduleItemCommentRequest $request, ScheduleItem $item): JsonResponse
    {
        $student = $this->assertOwnedByStudent($request, $item);

        $comment = new ItemComment([
            'comment_text' => $request->validated('comment_text'),
        ]);
        $comment->tenant_id = $student->tenant_id;
        $comment->item_id = $item->id;
        $comment->commenter_type = 'student';
        $comment->commenter_user_id = null;
        $comment->commenter_student_id = $student->id;
        $comment->save();

        return response()->json([
            'success' => true,
            'comment' => [
                'username' => $comment->commenterName(),
                'comment_text' => $comment->comment_text,
                'created_at' => optional($comment->created_at)->format('Y-m-d H:i'),
                'author' => 'student',
            ],
        ], 201);
    }

    /** A block's thread, oldest first. */
    public function comments(Request $request, ScheduleItem $item): JsonResponse
    {
        $this->assertOwnedByStudent($request, $item);

        return response()->json($item->comments()->get()->map(fn (ItemComment $comment) => [
            'username' => $comment->commenterName(),
            'comment_text' => $comment->comment_text,
            'created_at' => optional($comment->created_at)->format('Y-m-d H:i'),
            'author' => $comment->commenter_type === 'student' ? 'student' : 'consultant',
        ])->values());
    }

    private function assertOwnedByStudent(Request $request, ScheduleItem $item): Student
    {
        $student = $request->user('student');

        abort_unless($student && (int) $item->student_id === (int) $student->id, 404);

        return $student;
    }

    /**
     * Saturday-anchored (Persian) week, mirroring the consultant editor:
     * Carbon::dayOfWeek is 0=Sun..6=Sat, so this is the same `(dow + 1) % 7`
     * mapping the grid's day_index uses.
     */
    private function resolveWeekStart(?string $requested): Carbon
    {
        $base = null;

        if ($requested && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $requested)) {
            try {
                $base = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
            } catch (Throwable) {
                $base = null;
            }
        }

        $base ??= Carbon::today();

        return $base->copy()->subDays(($base->dayOfWeek + 1) % 7)->startOfDay();
    }
}
