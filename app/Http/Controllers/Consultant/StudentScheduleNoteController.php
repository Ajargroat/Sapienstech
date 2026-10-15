<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultant\StoreScheduleWeekNotesRequest;
use App\Models\ItemComment;
use App\Models\ScheduleItem;
use App\Models\ScheduleWeekNote;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The parts of the weekly planner that are not block CRUD: the per-weekday
 * notes the consultant leaves for the student, the title suggestions drawn
 * from this tenant's own plan history, and the consultant's replies on a
 * block's comment thread.
 *
 * Split out of StudentScheduleController so a 500-line class does not become a
 * 900-line one. The isolation rules are identical and are restated here rather
 * than inherited: {student} is re-checked against tenant() (fail closed, 404)
 * and every note/comment row is written with tenant_id from tenant(), never
 * from request input.
 */
class StudentScheduleNoteController extends Controller
{
    /** Every weekday's text for one saved week. */
    public function notes(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);

        $weekStart = $this->resolveWeekStart($request->query('week_start_date'));

        return response()->json($this->weekNotesPayload($student, $weekStart));
    }

    /**
     * Save a whole week of notes in one request.
     *
     * A day present in `notes` with an empty string is a deliberate deletion
     * (its row is removed); a day absent from `notes` is untouched. Saving is
     * idempotent, so re-submitting the same payload writes nothing new.
     */
    public function updateNotes(StoreScheduleWeekNotesRequest $request, Student $student): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);

        $weekStart = $this->resolveWeekStart($request->validated('week_start_date'));
        $byDay = $request->notesByDay();
        $userId = $request->user()->id;

        DB::transaction(function () use ($student, $weekStart, $byDay, $userId) {
            foreach ($byDay as $dayIndex => $text) {
                $query = ScheduleWeekNote::query()
                    ->where('student_id', $student->id)
                    ->whereDate('week_start_date', $weekStart->toDateString())
                    ->where('day_index', $dayIndex);

                if ($text === null || $text === '') {
                    $query->delete();

                    continue;
                }

                $note = $query->first() ?? new ScheduleWeekNote;
                $note->tenant_id = tenant()->id;
                $note->student_id = $student->id;
                $note->week_start_date = $weekStart->toDateString();
                $note->day_index = $dayIndex;
                $note->note_text = $text;
                $note->updated_by_user_id = $userId;
                $note->save();
            }
        });

        return response()->json([
            'success' => true,
            'updated_days' => array_keys(array_filter($byDay, fn ($text) => $text !== null && $text !== '')),
            'deleted_days' => array_keys(array_filter($byDay, fn ($text) => $text === null || $text === '')),
        ] + $this->weekNotesPayload($student, $weekStart) + ['week' => $this->weekNotesPayload($student, $weekStart)]);
    }

    /**
     * Title suggestions for the block dialog's datalist.
     *
     * The pool is *this tenant's* own block titles, never a shared one: the
     * point is to help a consultant reuse their own wording (and notice when
     * they are about to add a block they already have) without shipping one
     * academy's syllabus to another. Titles are grouped instead of listed raw
     * so a task repeated twenty times still counts once.
     *
     * Reading history is not gated on `week_start_date`, so a phrase learned in
     * September still suggests itself in December.
     *
     * Each title also carries the colour the consultant *usually* reaches for on
     * it -- the most-used colour across that title's history. Picking a
     * suggestion then keeps the whole schedule coherent instead of forcing the
     * consultant to re-decide a colour they already decided months ago. Ties are
     * broken by the colour used most recently, so a deliberate change of habit
     * wins over a stale count.
     */
    public function suggestions(Request $request, Student $student): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);

        $limit = (int) $request->query('limit', 0);
        $limit = $limit >= 1 && $limit <= 200 ? $limit : 75;

        $suggestions = ScheduleItem::query()
            ->where('tenant_id', tenant()->id)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->select('title', DB::raw('COUNT(*) as usage_count'), DB::raw('MAX(created_at) as last_used_at'))
            ->groupBy('title')
            ->orderByDesc('usage_count')
            ->orderByDesc('last_used_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'title' => (string) $row->title,
                'usage_count' => (int) $row->usage_count,
                'last_used_at' => $row->last_used_at,
            ])
            ->values();

        // One extra question, asked once for the whole page: which colour does
        // this consultant habitually use on each of these titles?
        $colours = $this->dominantColours($suggestions->pluck('title')->all());

        $suggestions = $suggestions->map(function (array $row) use ($colours) {
            $row['color'] = $colours[$row['title']] ?? null;

            return $row;
        })->values();

        return response()->json([
            'suggestions' => $suggestions,
            'scope' => 'tenant',
        ]);
    }

    /** The consultant's reply on a block's thread. */
    public function storeComment(Request $request, Student $student, ScheduleItem $item): JsonResponse
    {
        $this->assertStudentBelongsToTenant($student);
        $this->assertItemBelongsToStudent($item, $student);

        $data = $request->validate([
            'comment_text' => ['required', 'string', 'max:2000'],
        ]);

        $comment = new ItemComment(['comment_text' => $data['comment_text']]);
        $comment->tenant_id = tenant()->id;
        $comment->item_id = $item->id;
        $comment->commenter_type = 'user';
        $comment->commenter_user_id = $request->user()->id;
        $comment->commenter_student_id = null;
        $comment->save();

        return response()->json([
            'success' => true,
            'comment' => [
                'username' => $comment->commenterName(),
                'comment_text' => $comment->comment_text,
                'created_at' => optional($comment->created_at)->format('Y-m-d H:i'),
                'author' => 'consultant',
            ],
        ], 201);
    }

    /**
     * One student-week of notes, always seven entries (one per weekday) so the
     * client never has to guess which days exist.
     *
     * @return array<string, mixed>
     */
    private function weekNotesPayload(Student $student, Carbon $weekStart): array
    {
        $stored = ScheduleWeekNote::query()
            ->where('student_id', $student->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->get()
            ->keyBy('day_index');

        $days = [];

        foreach (ScheduleWeekNote::DAYS as $index => $label) {
            $note = $stored->get($index);
            $text = $note?->note_text;

            $days[$index] = [
                'day_index' => $index,
                'label' => $label,
                'date' => $weekStart->copy()->addDays($index)->toDateString(),
                'text' => $text,
                'has_text' => is_string($text) && trim($text) !== '',
                'updated_at' => optional($note?->updated_at)->format('Y-m-d H:i'),
            ];
        }

        return [
            'week_start_date' => $weekStart->toDateString(),
            'days' => array_values($days),
            'note_count' => count(array_filter($days, fn (array $day) => $day['has_text'])),
        ];
    }

    /**
     * The most-used colour per title, for the titles just selected.
     *
     * Grouped in one query rather than one per title, and ranked by count first
     * then recency, so a title's dominant colour is what the consultant reaches
     * for by habit -- while a title whose usage has recently moved to a new
     * colour follows the current habit instead of an old lead.
     *
     * @param  list<string>  $titles
     * @return array<string, string>
     */
    private function dominantColours(array $titles): array
    {
        if ($titles === []) {
            return [];
        }

        $rows = ScheduleItem::query()
            ->where('tenant_id', tenant()->id)
            ->whereIn('title', $titles)
            ->whereNotNull('color')
            ->where('color', '!=', '')
            ->select(
                'title',
                'color',
                DB::raw('COUNT(*) as color_count'),
                DB::raw('MAX(created_at) as color_last_used_at'),
            )
            ->groupBy('title', 'color')
            ->orderBy('title')
            ->orderByDesc('color_count')
            ->orderByDesc('color_last_used_at')
            ->get();

        $dominant = [];

        foreach ($rows as $row) {
            // First row per title wins: the query is already ranked.
            $dominant[(string) $row->title] ??= (string) $row->color;
        }

        return $dominant;
    }

    private function assertStudentBelongsToTenant(Student $student): void
    {
        $tenant = tenant();

        abort_unless($tenant && (int) $student->tenant_id === (int) $tenant->id, 404);
    }

    private function assertItemBelongsToStudent(ScheduleItem $item, Student $student): void
    {
        abort_unless((int) $item->student_id === (int) $student->id, 404);
    }

    /** Same Saturday-anchored week the rest of the planner uses. */
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
