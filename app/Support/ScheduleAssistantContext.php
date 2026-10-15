<?php

namespace App\Support;

use App\Models\ScheduleItem;
use App\Models\ScheduleWeekNote;
use App\Models\Student;

/**
 * What the model is allowed to see about a student, and what it is allowed to
 * propose back.
 *
 * This class is the trust boundary in both directions, and both halves are
 * allowlists rather than denylists on purpose:
 *
 *   build()   — the context is assembled field by field from named keys. No
 *               model is ever `->toArray()`d into the prompt, so a column added
 *               to `students` or `schedule_items` later cannot silently start
 *               travelling to a third-party API. A test asserts the emitted
 *               payload contains none of the sensitive column names even when
 *               those columns are populated, which is the difference between
 *               "we intended not to leak" and "we can prove we do not".
 *
 *   sanitizeSuggestions() — the model's proposals are rebuilt from scratch into
 *               a fixed shape, and anything unrecognised is dropped, not
 *               coerced. A hallucinated block id, a day index of 9, a 10 MB
 *               string or an injected `student_id` all die here, before they can
 *               reach the UI.
 *
 * Deliberately NOT sent: email, phone, national id, bio, password material,
 * payment/deal amounts, chat messages, other students, tenant identity or any
 * internal row id beyond the block id needed to target an edit.
 */
class ScheduleAssistantContext
{
    /** Columns that must never appear in a prompt, whatever the schema becomes. */
    public const FORBIDDEN_KEYS = [
        'email', 'phone', 'mobile', 'national_id', 'password', 'remember_token',
        'bio', 'address', 'postal_code', 'bank', 'card', 'sheba', 'amount',
        'price', 'paid', 'payment', 'deal', 'tenant_id', 'user_id', 'domain_id',
    ];

    /** Colour literals we are willing to pass through to a colour column. */
    private const HEX = '/^#[0-9a-fA-F]{6}$/';

    /**
     * Build the untrusted-data blob handed to the model.
     *
     * Everything here is data the consultant could see anyway, trimmed to a
     * bounded size: `max_context_blocks` caps the prompt (a bulk-imported
     * tenant could otherwise build a megabyte prompt), and notes are clipped to
     * `max_note_chars`. Bounds are not politeness — an unbounded prompt is an
     * unbounded bill.
     *
     * @param  iterable<ScheduleItem>  $items
     * @param  iterable<ScheduleWeekNote>  $notes
     * @return array<string, mixed>
     */
    public function build(Student $student, iterable $items, iterable $notes, string $weekStart): array
    {
        $maxBlocks = $this->limit('ai.max_context_blocks', 60, 5, 300);
        $maxNote = $this->limit('ai.max_note_chars', 1200, 100, 4000);

        $blocks = [];
        foreach ($items as $item) {
            if (count($blocks) >= $maxBlocks) {
                break;
            }

            $blocks[] = array_filter([
                // The one id we do send: an edit suggestion must name the block
                // it wants changed, and the controller re-checks it against the
                // student before anything is applied.
                'id' => (int) $item->id,
                'title' => $this->clip($item->title, 160),
                'day' => $this->dayLabel($item),
                'date' => $item->start_datetime?->toDateString(),
                'start' => $item->start_datetime?->format('H:i'),
                'end' => $item->end_datetime?->format('H:i'),
                'color' => $this->colour($item->color),
                'description' => $this->clip($item->description, 300),
                'book' => $this->clip($item->book_name, 120),
                'pages' => $item->page_count !== null ? (int) $item->page_count : null,
                'tests' => $item->test_count !== null ? (int) $item->test_count : null,
                'done' => (bool) $item->is_completed,
                // Whether the consultant wrote this or the student did. The
                // model is told, because the two carry different authority.
                'origin' => $item->item_type === 'student_personal_block' ? 'student' : 'consultant',
            ], fn ($v) => $v !== null && $v !== '');
        }

        $dayNotes = [];
        foreach ($notes as $note) {
            $text = $this->clip($note->note_text, $maxNote);

            if ($text !== null) {
                $dayNotes[] = [
                    'day' => ScheduleWeekNote::DAYS[(int) $note->day_index] ?? (string) $note->day_index,
                    'text' => $text,
                ];
            }
        }

        return [
            // Display name only, and explicitly marked as display: a full
            // identity is not needed to advise on a timetable.
            'student' => [
                'name' => $this->clip($student->name, 120),
                'grade' => $this->clip($student->grade, 60),
                'major' => $this->clip($student->major, 60),
            ],
            'week_start' => $weekStart,
            'blocks' => $blocks,
            'week_notes' => $dayNotes,
            'block_count' => count($blocks),
            'truncated' => count($blocks) >= $maxBlocks,
        ];
    }

    /**
     * Rebuild the model's suggestions into a shape the UI can trust.
     *
     * Every suggestion is reconstructed field by field; unknown keys are
     * discarded, not merged through. A suggestion that cannot be made valid is
     * dropped entirely — it is better to show four usable cards than five where
     * one silently writes the wrong thing.
     *
     * Confidence is the capability gate, not decoration: only `high` earns an
     * actionable card. Anything vaguer is returned with `apply: false` so it
     * renders in an ideas lane *and* is physically unable to mutate a schedule —
     * the "only if it is really sure" rule enforced in code rather than asked
     * for in the prompt.
     *
     * @param  mixed  $raw  decoded JSON from the model
     * @param  array<int, int>  $allowedItemIds  blocks belonging to THIS student
     * @return list<array<string, mixed>>
     */
    public function sanitizeSuggestions(mixed $raw, array $allowedItemIds): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $kind = is_string($entry['kind'] ?? null) ? strtolower(trim($entry['kind'])) : '';
            $confidence = is_string($entry['confidence'] ?? null) ? strtolower(trim($entry['confidence'])) : 'low';
            $confidence = in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : 'low';

            $title = $this->clip($entry['title'] ?? null, 160);
            $reason = $this->clip($entry['reason'] ?? null, 400);

            $card = match ($kind) {
                'add_block' => $this->addBlockCard($entry, $title, $reason, $confidence),
                'edit_block' => $this->editBlockCard($entry, $allowedItemIds, $title, $reason, $confidence),
                'week_note' => $this->weekNoteCard($entry, $title, $reason, $confidence),
                // 'advice' and anything unrecognised: prose only, never
                // actionable. Unknown kinds are intentionally not error-paged —
                // a model emitting a new kind must not break the panel, it must
                // simply not be able to do anything.
                default => $this->adviceCard($title, $reason, $confidence),
            };

            if ($card !== null) {
                $out[] = $card;
            }

            // A chatty model must not be able to flood the panel either.
            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Suggestion shapes
    // -------------------------------------------------------------------------

    private function addBlockCard(array $entry, ?string $title, ?string $reason, string $confidence): ?array
    {
        $day = $this->dayIndex($entry['day_index'] ?? null);
        $start = $this->time($entry['start_time'] ?? null);
        $end = $this->time($entry['end_time'] ?? null);

        // An add without a valid slot is not actionable, but the *advice* may
        // still be worth reading, so it degrades to prose rather than vanishing.
        if ($title === null || $day === null || $start === null || $end === null || $end <= $start) {
            return $this->adviceCard($title, $reason, $confidence);
        }

        return [
            'kind' => 'add_block',
            'confidence' => $confidence,
            // Applying replays this payload through the EXISTING store endpoint,
            // which re-validates it. The card is a prefill, never an authority.
            'apply' => $confidence === 'high',
            'payload' => array_filter([
                'title' => $title,
                'day_index' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'color' => $this->colour($entry['color'] ?? null),
                'description' => $this->clip($entry['description'] ?? null, 1000),
                'book_name' => $this->clip($entry['book_name'] ?? null, 120),
                'page_count' => $this->intRange($entry['page_count'] ?? null, 0, 10000),
                'test_count' => $this->intRange($entry['test_count'] ?? null, 0, 10000),
            ], fn ($v) => $v !== null && $v !== ''),
            'label' => $title,
            'reason' => $reason,
        ];
    }

    private function editBlockCard(array $entry, array $allowedItemIds, ?string $title, ?string $reason, string $confidence): ?array
    {
        $itemId = $this->intRange($entry['item_id'] ?? null, 1, PHP_INT_MAX);

        // A suggestion targeting a block that is not this student's is dropped
        // outright: it is either a hallucination or an attempt to reach across
        // students, and neither deserves a card.
        if ($itemId === null || ! in_array($itemId, $allowedItemIds, true)) {
            return null;
        }

        $changes = array_filter([
            'title' => $this->clip(data_get($entry, 'changes.title'), 160),
            'description' => $this->clip(data_get($entry, 'changes.description'), 1000),
            'color' => $this->colour(data_get($entry, 'changes.color')),
            'book_name' => $this->clip(data_get($entry, 'changes.book_name'), 120),
            'page_count' => $this->intRange(data_get($entry, 'changes.page_count'), 0, 10000),
            'test_count' => $this->intRange(data_get($entry, 'changes.test_count'), 0, 10000),
        ], fn ($v) => $v !== null && $v !== '');

        // An "edit" that changes nothing is noise.
        if ($changes === []) {
            return null;
        }

        return [
            'kind' => 'edit_block',
            'confidence' => $confidence,
            'apply' => $confidence === 'high',
            'item_id' => $itemId,
            'changes' => $changes,
            'label' => $title ?? $this->clip(data_get($entry, 'changes.title'), 60),
            'reason' => $reason,
        ];
    }

    private function weekNoteCard(array $entry, ?string $title, ?string $reason, string $confidence): ?array
    {
        $day = $this->dayIndex($entry['day_index'] ?? null);
        $text = $this->clip($entry['text'] ?? $entry['note_text'] ?? null, 2000);

        if ($day === null || $text === null) {
            return $this->adviceCard($title, $reason, $confidence);
        }

        return [
            'kind' => 'week_note',
            'confidence' => $confidence,
            'apply' => $confidence === 'high',
            'day_index' => $day,
            'text' => $text,
            'label' => $title ?? $text,
            'reason' => $reason,
        ];
    }

    private function adviceCard(?string $title, ?string $reason, string $confidence): ?array
    {
        $text = $title ?? $reason;

        if ($text === null) {
            return null;
        }

        return [
            'kind' => 'advice',
            'confidence' => $confidence,
            // The structural half of "only suggest changes when it is really
            // sure": advice can never be applied, at any confidence level.
            'apply' => false,
            'label' => $text,
            'reason' => $reason,
        ];
    }

    // -------------------------------------------------------------------------
    // Coercion helpers — every one returns null rather than throwing
    // -------------------------------------------------------------------------

    private function clip(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    private function colour(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match(self::HEX, trim($value))) {
            return null;
        }

        return strtolower(trim($value));
    }

    private function dayIndex(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $day = (int) $value;

        return $day >= 0 && $day <= 6 ? $day : null;
    }

    /** Accepts "17:00" or "17:00:00"; rejects anything else. */
    private function time(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', trim($value))) {
            return null;
        }

        return substr(trim($value), 0, 5);
    }

    private function intRange(mixed $value, int $min, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= $min && $int <= $max ? $int : null;
    }

    private function dayLabel(ScheduleItem $item): ?string
    {
        if (! $item->start_datetime) {
            return null;
        }

        // Carbon dayOfWeek is 0=Sun..6=Sat; the grid is Saturday-first.
        $index = ($item->start_datetime->dayOfWeek + 1) % 7;

        return ScheduleWeekNote::DAYS[$index] ?? null;
    }

    private function limit(string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int) site($key, $default)));
    }
}
