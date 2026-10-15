<?php

namespace Tests\Unit;

use App\Models\ScheduleItem;
use App\Models\ScheduleWeekNote;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\ScheduleAssistantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trust boundary of the assistant, tested where it actually lives.
 *
 * Two properties matter more than anything else in this feature, and both are
 * tested here rather than through the HTTP layer, because they are pure
 * functions over data:
 *
 *  1. build() must not be able to leak a sensitive column. The test populates
 *     the sensitive fields and then asserts they are absent from the emitted
 *     payload — so the guarantee survives someone adding a column later.
 *
 *  2. sanitizeSuggestions() must drop anything it cannot fully vouch for. A
 *     hallucinated block id, an out-of-range day, a non-hex colour or a
 *     suggestion aimed at another student must never reach a card. This is what
 *     makes the model's output safe to render, and it is tested adversarially.
 */
class ScheduleAssistantContextTest extends TestCase
{
    use RefreshDatabase;

    private function context(): ScheduleAssistantContext
    {
        return new ScheduleAssistantContext;
    }

    private function student(array $overrides = []): Student
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        // Only the columns `students` actually has. The sensitive ones that DO
        // exist are populated for real, so the redaction assertions below cannot
        // pass vacuously against an empty row.
        return Student::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'علی محمدی',
            'email' => 'ali@example.test',
            'password' => bcrypt('super-secret-password'),
            'grade' => 'دوازدهم',
            'gender' => 'پسر',
            'major' => 'تجربی',
        ], $overrides));
    }

    // =========================================================================
    // The redaction contract
    // =========================================================================

    public function test_the_context_never_contains_identifying_or_sensitive_fields(): void
    {
        $student = $this->student();

        $payload = $this->context()->build($student, collect(), collect(), '2026-10-17');
        $flat = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // The populated sensitive values are asserted present on the model
        // first — otherwise "not in the payload" would prove nothing.
        $this->assertSame('ali@example.test', $student->email);
        $this->assertNotEmpty($student->password);

        foreach (ScheduleAssistantContext::FORBIDDEN_KEYS as $key) {
            $this->assertStringNotContainsString($key, $flat, "the context leaked `{$key}`");
        }

        $this->assertStringNotContainsString('ali@example.test', $flat);
        // Even the *hash* must not travel; that is the whole point.
        $this->assertStringNotContainsString($student->password, $flat);
    }

    public function test_the_context_carries_only_the_display_name_and_roster_shape(): void
    {
        $student = $this->student();

        $payload = $this->context()->build($student, collect(), collect(), '2026-10-17');

        $this->assertSame('علی محمدی', $payload['student']['name']);
        $this->assertSame('دوازدهم', $payload['student']['grade']);
        $this->assertSame('تجربی', $payload['student']['major']);
        $this->assertSame('2026-10-17', $payload['week_start']);
    }

    public function test_blocks_are_summarised_without_internal_identifiers_beyond_the_block_id(): void
    {
        $student = $this->student();
        $item = ScheduleItem::factory()->create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'title' => 'حل تمرین فصل ۳',
            'book_name' => 'ریاضی ۳',
            'page_count' => 20,
            'test_count' => 2,
            'color' => '#3b82f6',
        ]);

        $payload = $this->context()->build($student, collect([$item]), collect(), '2026-10-17');
        $block = $payload['blocks'][0];

        $this->assertSame($item->id, $block['id']);
        $this->assertSame('حل تمرین فصل ۳', $block['title']);
        $this->assertSame('ریاضی ۳', $block['book']);
        $this->assertSame(20, $block['pages']);
        $this->assertSame(2, $block['tests']);
        $this->assertSame('#3b82f6', $block['color']);
        $this->assertSame('consultant', $block['origin']);

        // tenant_id and student_id are the isolation keys; they do not travel.
        $this->assertArrayNotHasKey('tenant_id', $block);
        $this->assertArrayNotHasKey('student_id', $block);
    }

    public function test_a_student_authored_block_is_marked_as_such(): void
    {
        $student = $this->student();
        $item = ScheduleItem::factory()->studentBlock()->create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
        ]);

        $payload = $this->context()->build($student, collect([$item]), collect(), '2026-10-17');

        // The model is told who wrote a block: student-authored free text must
        // not be treated with the same authority as the consultant's own.
        $this->assertSame('student', $payload['blocks'][0]['origin']);
    }

    public function test_the_block_list_is_capped_and_reports_truncation(): void
    {
        $student = $this->student();
        // `ai` is a top-level key of the resolved site config (like `chat` and
        // `features`), NOT nested under `theme`.
        site_override(['ai' => ['max_context_blocks' => 5]]);

        $items = ScheduleItem::factory()->count(9)->create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
        ]);

        $payload = $this->context()->build($student, $items, collect(), '2026-10-17');

        // An unbounded prompt is an unbounded bill; the cap must hold and must
        // be reported so the prompt can mention that data was trimmed.
        $this->assertCount(5, $payload['blocks']);
        $this->assertTrue($payload['truncated']);
    }

    public function test_week_notes_are_included_and_clipped(): void
    {
        $student = $this->student();
        // The builder clamps this knob to a sane floor (100 chars) so a bad
        // tenant value cannot make notes useless — so the test drives it with a
        // realistic limit rather than an absurd one.
        site_override(['ai' => ['max_note_chars' => 120]]);

        $note = ScheduleWeekNote::create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'week_start_date' => '2026-10-17',
            'day_index' => 2,
            'note_text' => str_repeat('یادداشت ', 100),
        ]);

        $payload = $this->context()->build($student, collect(), collect([$note]), '2026-10-17');

        $this->assertSame('دوشنبه', $payload['week_notes'][0]['day']);
        $this->assertSame(120, mb_strlen($payload['week_notes'][0]['text']));
        // The clipping must be real clipping, not a dropped field.
        $this->assertLessThan(mb_strlen($note->note_text), mb_strlen($payload['week_notes'][0]['text']));
    }

    // =========================================================================
    // Suggestion sanitising — adversarial
    // =========================================================================

    public function test_a_well_formed_suggestion_survives_with_an_apply_action(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'high',
            'title' => 'مرور آزمون فصل ۳',
            'reason' => 'جمعه‌های سه هفته اخیر آزمون بوده است.',
            'day_index' => 5,
            'start_time' => '17:00',
            'end_time' => '18:30',
            'color' => '#3b82f6',
            'book_name' => 'ریاضی ۳',
            'page_count' => 20,
            'test_count' => 1,
        ]], []);

        $this->assertCount(1, $suggestions);
        $this->assertTrue($suggestions[0]['apply']);
        $this->assertSame('add_block', $suggestions[0]['kind']);
        $this->assertSame(5, $suggestions[0]['payload']['day_index']);
        $this->assertSame('17:00', $suggestions[0]['payload']['start_time']);
        $this->assertSame(20, $suggestions[0]['payload']['page_count']);
    }

    public function test_a_low_confidence_suggestion_is_never_appliable(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'low',
            'title' => 'شاید ورزش',
            'day_index' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]], []);

        // The "only if it is really sure" rule enforced in code: the card is
        // returned but structurally cannot mutate a schedule.
        $this->assertCount(1, $suggestions);
        $this->assertFalse($suggestions[0]['apply']);
    }

    public function test_advice_is_never_appliable_at_any_confidence(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'advice',
            'confidence' => 'high',
            'title' => 'بهتر است دانش‌آموز بیشتر استراحت کند',
        ]], []);

        $this->assertCount(1, $suggestions);
        $this->assertFalse($suggestions[0]['apply']);
        $this->assertSame('advice', $suggestions[0]['kind']);
    }

    public function test_an_edit_targeting_another_students_block_is_dropped(): void
    {
        $student = $this->student();
        $mine = ScheduleItem::factory()->create(['tenant_id' => $student->tenant_id, 'student_id' => $student->id]);
        $theirs = ScheduleItem::factory()->create(['tenant_id' => $student->tenant_id, 'student_id' => $student->id]);

        $suggestions = $this->context()->sanitizeSuggestions([
            ['kind' => 'edit_block', 'confidence' => 'high', 'item_id' => $mine->id,
                'title' => 'افزودن صفحه', 'changes' => ['page_count' => 30]],
            ['kind' => 'edit_block', 'confidence' => 'high', 'item_id' => $theirs->id + 9999,
                'title' => 'دستکاری', 'changes' => ['page_count' => 99]],
        ], [(int) $mine->id]);

        // The reachable one survives, the unreachable one does not exist at all.
        $this->assertCount(1, $suggestions);
        $this->assertSame($mine->id, $suggestions[0]['item_id']);
    }

    public function test_an_out_of_range_day_index_degrades_to_advice_instead_of_a_card(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'high',
            'title' => 'روز نامعتبر',
            'day_index' => 9,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]], []);

        // It must not become an add_block card (which would carry day 9 into a
        // form), but deleting the text would throw away whatever the model
        // actually meant. It becomes readable, inert prose.
        $this->assertCount(1, $suggestions);
        $this->assertSame('advice', $suggestions[0]['kind']);
        $this->assertFalse($suggestions[0]['apply']);
    }

    public function test_an_end_time_before_the_start_time_is_not_actionable(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'high',
            'title' => 'بازه معکوس',
            'day_index' => 2,
            'start_time' => '18:00',
            'end_time' => '17:00',
        ]], []);

        $this->assertSame('advice', $suggestions[0]['kind']);
    }

    public function test_non_hex_colours_are_stripped_not_stored(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'high',
            'title' => 'رنگ تزریقی',
            'day_index' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            // A value that would break out of a style attribute if rendered raw.
            'color' => 'red; background:url(javascript:alert(1))',
        ]], []);

        $this->assertArrayNotHasKey('color', $suggestions[0]['payload']);
    }

    public function test_malformed_time_strings_are_rejected(): void
    {
        foreach (['25:00', '10', '10:99', 'abc', '10:00; DROP TABLE'] as $bad) {
            $suggestions = $this->context()->sanitizeSuggestions([[
                'kind' => 'add_block',
                'confidence' => 'high',
                'title' => 'زمان بد',
                'day_index' => 1,
                'start_time' => $bad,
                'end_time' => '11:00',
            ]], []);

            $this->assertSame('advice', $suggestions[0]['kind'], "accepted bad time `{$bad}`");
        }
    }

    public function test_unexpected_keys_are_not_carried_through(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'add_block',
            'confidence' => 'high',
            'title' => 'عنوان',
            'day_index' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            // A model attempting to set the isolation keys directly.
            'tenant_id' => 999,
            'student_id' => 999,
            'item_type' => 'consultant_event',
            'is_completed' => true,
        ]], []);

        $payload = $suggestions[0]['payload'];
        $this->assertArrayNotHasKey('tenant_id', $payload);
        $this->assertArrayNotHasKey('student_id', $payload);
        $this->assertArrayNotHasKey('item_type', $payload);
        $this->assertArrayNotHasKey('is_completed', $payload);
    }

    public function test_a_non_array_suggestion_list_is_ignored(): void
    {
        $this->assertSame([], $this->context()->sanitizeSuggestions('not an array', []));
        $this->assertSame([], $this->context()->sanitizeSuggestions(null, []));
    }

    public function test_the_suggestion_count_is_capped(): void
    {
        $many = [];
        for ($i = 0; $i < 40; $i++) {
            $many[] = ['kind' => 'advice', 'confidence' => 'low', 'title' => "ایده {$i}"];
        }

        // A chatty model must not be able to flood the panel either.
        $this->assertCount(12, $this->context()->sanitizeSuggestions($many, []));
    }

    public function test_over_long_text_is_clipped_rather_than_stored(): void
    {
        $suggestions = $this->context()->sanitizeSuggestions([[
            'kind' => 'advice',
            'confidence' => 'low',
            'title' => str_repeat('ا', 5000),
        ]], []);

        $this->assertSame(160, mb_strlen($suggestions[0]['label']));
    }
}
