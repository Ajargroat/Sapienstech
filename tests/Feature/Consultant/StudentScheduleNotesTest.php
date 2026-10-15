<?php

namespace Tests\Feature\Consultant;

use App\Http\Middleware\ApplyPersonalTheme;
use App\Http\Middleware\EnsureUserDomain;
use App\Http\Middleware\IdentifyTenant;
use App\Models\ScheduleItem;
use App\Models\ScheduleWeekNote;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SiteConfig;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * The consultant-side additions to the weekly planner: weekly notes
 * (توضیحات هفته), the tenant-scoped title suggestions, and the consultant's
 * reply on a block's comment thread.
 *
 * These assert isolation as hard as they assert the feature: every one of them
 * is a new endpoint reaching student-owned data.
 */
class StudentScheduleNotesTest extends TestCase
{
    private User $consultant;

    private Student $student;

    private Student $otherStudent;

    private Student $foreignStudent;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'schedule_notes_test',
            'database.connections.schedule_notes_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'app.key' => 'base64:'.base64_encode(str_repeat('n', 32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        foreach (['tenants', 'users', 'students'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                if ($tableName !== 'tenants') {
                    $table->foreignId('tenant_id')->constrained('tenants');
                }
                if ($tableName === 'students') {
                    // Soft roster marker (Student's `roster` global scope).
                    $table->timestamp('removed_at')->nullable();
                }
            });
        }

        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('student_id')->constrained();
            $table->date('week_start_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_datetime');
            $table->dateTime('end_datetime');
            $table->string('color', 50);
            $table->string('item_type');
            $table->string('created_by_type');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->foreignId('created_by_student_id')->nullable()->constrained('students');
            $table->string('book_name')->nullable();
            $table->string('link_url', 2083)->nullable();
            $table->integer('page_count')->nullable();
            $table->integer('test_count')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completion_timestamp')->nullable();
            $table->unsignedBigInteger('bulk_action_id')->nullable();
            $table->timestamps();
        });

        Schema::create('item_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('item_id')->constrained('schedule_items');
            $table->string('commenter_type');
            $table->foreignId('commenter_user_id')->nullable()->constrained('users');
            $table->foreignId('commenter_student_id')->nullable()->constrained('students');
            $table->text('comment_text');
            $table->timestamp('created_at')->useCurrent();
        });

        // The real migration, so the schema under test is the shipped one.
        (require database_path('migrations/2026_10_20_000001_create_schedule_week_notes_table.php'))->up();

        DB::table('tenants')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert([
            ['id' => 1, 'tenant_id' => 1],
            ['id' => 3, 'tenant_id' => 2],
        ]);
        DB::table('students')->insert([
            ['id' => 1, 'tenant_id' => 1],
            ['id' => 2, 'tenant_id' => 1],
            ['id' => 3, 'tenant_id' => 2],
        ]);

        $this->app->instance('tenant', (new Tenant)->forceFill(['id' => 1]));
        $this->consultant = (new User)->forceFill(['id' => 1, 'tenant_id' => 1, 'role' => 'consultant_staff']);
        $this->student = Student::findOrFail(1);
        $this->otherStudent = Student::findOrFail(2);
        // staff_access deliberately hides other tenants students from the acting
        // consultant, so the foreign row is resolved off-scope by id.
        $this->foreignStudent = Student::withoutGlobalScopes()->findOrFail(3);
        $this->actingAs($this->consultant);

        $this->withoutMiddleware([IdentifyTenant::class, EnsureUserDomain::class, ApplyPersonalTheme::class]);

        $site = Mockery::mock(SiteConfig::class);
        $site->shouldReceive('all')->andReturn(['features' => ['student_schedule' => true]]);
        $this->app->instance(SiteConfig::class, $site);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            DB::purge('schedule_notes_test');
        }

        parent::tearDown();
    }

    private function notesUrl(?Student $student = null): string
    {
        return '/consultant/students/'.($student?->id ?? $this->student->id).'/schedule/notes';
    }

    private function weekStart(): string
    {
        return Carbon::now()->startOfWeek(Carbon::SATURDAY)->toDateString();
    }

    private function item(?Student $student = null, array $attributes = []): ScheduleItem
    {
        return ScheduleItem::create(array_replace([
            'tenant_id' => $student?->tenant_id ?? 1,
            'student_id' => $student?->id ?? $this->student->id,
            'week_start_date' => $this->weekStart(),
            'title' => 'مطالعه ریاضی دهم',
            'start_datetime' => $this->weekStart().' 09:00:00',
            'end_datetime' => $this->weekStart().' 10:00:00',
            'color' => '#3b82f6',
            'item_type' => 'consultant_event',
            'created_by_type' => 'user',
            'created_by_user_id' => 1,
        ], $attributes));
    }

    /* ------------------------------------------------------------ notes --- */

    public function test_notes_endpoint_returns_seven_days_for_an_empty_week(): void
    {
        $response = $this->getJson($this->notesUrl().'?week_start_date='.$this->weekStart());

        $response->assertOk()
            ->assertJsonPath('week_start_date', $this->weekStart())
            ->assertJsonPath('note_count', 0)
            ->assertJsonCount(7, 'days');

        foreach ($response->json('days') as $index => $day) {
            $this->assertSame($index, $day['day_index'], 'days are in Saturday-first order');
            $this->assertFalse($day['has_text']);
            $this->assertNull($day['text']);
        }
    }

    public function test_consultant_can_save_a_whole_week_of_notes(): void
    {
        $week = $this->weekStart();

        $this->putJson($this->notesUrl(), [
            'week_start_date' => $week,
            'notes' => [0 => 'شنبه: مرور فصل دوم', 3 => '  سه‌شنبه: آزمون آزمایشی  '],
        ])->assertOk()->assertJsonPath('success', true);

        // Text is trimmed on the way in, and untouched days are not written.
        $this->assertDatabaseHas('schedule_week_notes', [
            'student_id' => $this->student->id,
            'tenant_id' => 1,
            // The cast writes a datetime-shaped string into sqlite.
            'week_start_date' => $week.' 00:00:00',
            'day_index' => 0,
            'note_text' => 'شنبه: مرور فصل دوم',
        ]);
        $this->assertDatabaseHas('schedule_week_notes', [
            'day_index' => 3,
            'note_text' => 'سه‌شنبه: آزمون آزمایشی',
        ]);
        $this->assertSame(2, ScheduleWeekNote::query()->count());
    }

    public function test_notes_are_scoped_to_their_week(): void
    {
        $this->putJson($this->notesUrl(), [
            'week_start_date' => $this->weekStart(),
            'notes' => [0 => 'این هفته'],
        ])->assertOk();

        $nextWeek = Carbon::parse($this->weekStart())->addWeek()->toDateString();

        $this->getJson($this->notesUrl().'?week_start_date='.$nextWeek)
            ->assertOk()
            ->assertJsonPath('note_count', 0);
    }

    public function test_emptying_a_day_deletes_its_note_and_leaves_others_alone(): void
    {
        $week = $this->weekStart();
        $this->putJson($this->notesUrl(), [
            'week_start_date' => $week,
            'notes' => [0 => 'بمان', 1 => 'حذف شود'],
        ])->assertOk();

        $this->putJson($this->notesUrl(), [
            'week_start_date' => $week,
            'notes' => [1 => ''],
        ])->assertOk();

        $this->assertDatabaseHas('schedule_week_notes', ['day_index' => 0, 'note_text' => 'بمان']);
        $this->assertDatabaseMissing('schedule_week_notes', ['day_index' => 1]);
    }

    public function test_unicode_note_survives_a_round_trip(): void
    {
        $week = $this->weekStart();
        $text = "خط اول\nخط دوم — «نقل قول» و ایموجی 📚";

        $this->putJson($this->notesUrl(), [
            'week_start_date' => $week,
            'notes' => [6 => $text],
        ])->assertOk();

        $response = $this->getJson($this->notesUrl().'?week_start_date='.$week);

        $this->assertSame($text, $response->json('days.6.text'));
        $this->assertTrue($response->json('days.6.has_text'));
    }

    public function test_notes_reject_a_note_longer_than_the_limit(): void
    {
        $this->putJson($this->notesUrl(), [
            'week_start_date' => $this->weekStart(),
            'notes' => [0 => str_repeat('ا', 2001)],
        ])->assertStatus(422)->assertJsonValidationErrors(['notes.0']);

        $this->assertSame(0, ScheduleWeekNote::query()->count());
    }

    public function test_notes_reject_a_malformed_week(): void
    {
        $this->putJson($this->notesUrl(), [
            'week_start_date' => 'not-a-date',
            'notes' => [0 => 'x'],
        ])->assertStatus(422)->assertJsonValidationErrors(['week_start_date']);
    }

    /* ----------------------------------------------------- suggestions --- */

    public function test_suggestions_are_drawn_from_this_tenants_own_history(): void
    {
        $this->item(attributes: ['title' => 'مطالعه ریاضی دهم']);
        $this->item(attributes: ['title' => 'مطالعه ریاضی دهم']);
        $this->item(attributes: ['title' => 'حل تست فیزیک']);

        // Another tenant's phrase must never appear in this tenant's pool.
        $this->item($this->foreignStudent, ['title' => 'برنامه محرمانه تننت دیگر']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $response->assertOk()->assertJsonPath('scope', 'tenant');

        $titles = array_column($response->json('suggestions'), 'title');

        $this->assertSame('مطالعه ریاضی دهم', $titles[0], 'most-used title ranks first');
        $this->assertContains('حل تست فیزیک', $titles);
        $this->assertNotContains('برنامه محرمانه تننت دیگر', $titles);
    }

    public function test_suggestions_group_repeats_and_report_their_usage(): void
    {
        $this->item(attributes: ['title' => 'مرور']);
        $this->item(attributes: ['title' => 'مرور']);
        $this->item(attributes: ['title' => 'مرور']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $response->assertOk()->assertJsonCount(1, 'suggestions');
        $this->assertSame(3, $response->json('suggestions.0.usage_count'));
    }

    public function test_suggestions_come_from_every_week_not_just_the_current_one(): void
    {
        $this->item(attributes: [
            'title' => 'فصل قدیمی',
            'week_start_date' => '2020-01-04',
            'start_datetime' => '2020-01-04 09:00:00',
            'end_datetime' => '2020-01-04 10:00:00',
        ]);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $this->assertContains('فصل قدیمی', array_column($response->json('suggestions'), 'title'));
    }

    public function test_suggestions_honour_the_limit_query(): void
    {
        foreach (range(1, 6) as $n) {
            $this->item(attributes: ['title' => 'تیتر '.$n]);
        }

        $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'suggestions');
    }

    public function test_a_suggestion_carries_the_colour_the_consultant_uses_most(): void
    {
        // Four green, one red: the habit is green, so the suggestion says green.
        foreach (range(1, 4) as $n) {
            $this->item(attributes: ['title' => 'ریاضی', 'color' => '#22c55e']);
        }
        $this->item(attributes: ['title' => 'ریاضی', 'color' => '#ef4444']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $response->assertOk();
        $this->assertSame('#22c55e', $response->json('suggestions.0.color'));
    }

    public function test_a_recent_colour_change_beats_a_stale_majority(): void
    {
        // Two blue blocks, then the consultant switches to teal twice. Counts
        // tie, so the MOST RECENT use decides -- a deliberate change of habit
        // must not be held hostage by an older majority.
        foreach (range(1, 2) as $n) {
            $this->item(attributes: ['title' => 'فیزیک', 'color' => '#3b82f6', 'created_at' => '2026-01-0'.$n.' 09:00:00']);
        }
        foreach (range(3, 4) as $n) {
            $this->item(attributes: ['title' => 'فیزیک', 'color' => '#2dd4bf', 'created_at' => '2026-02-0'.$n.' 09:00:00']);
        }

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $this->assertSame('#2dd4bf', $response->json('suggestions.0.color'));
    }

    public function test_a_colour_is_chosen_per_title_not_across_the_whole_tenant(): void
    {
        $this->item(attributes: ['title' => 'ریاضی', 'color' => '#22c55e']);
        $this->item(attributes: ['title' => 'ادبیات', 'color' => '#fb7185']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $byTitle = collect($response->json('suggestions'))->keyBy('title');
        $this->assertSame('#22c55e', $byTitle['ریاضی']['color']);
        $this->assertSame('#fb7185', $byTitle['ادبیات']['color']);
    }

    public function test_a_second_tenants_habit_never_colours_this_tenants_suggestion(): void
    {
        $this->item(attributes: ['title' => 'مشترک', 'color' => '#22c55e']);

        // The foreign tenant uses the same title with a different colour, and
        // far more often -- none of which may bleed across.
        foreach (range(1, 9) as $n) {
            $this->item($this->foreignStudent, ['title' => 'مشترک', 'color' => '#ef4444']);
        }

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $row = collect($response->json('suggestions'))->firstWhere('title', 'مشترک');
        $this->assertSame('#22c55e', $row['color']);
        $this->assertSame(1, $row['usage_count']);
    }

    public function test_a_title_with_no_colour_history_reports_a_null_colour(): void
    {
        $this->item(attributes: ['title' => 'بی‌رنگ', 'color' => '']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $this->assertNull($response->json('suggestions.0.color'));
    }
    /* -------------------------------------------------------- comments --- */

    public function test_consultant_can_reply_on_a_blocks_thread(): void
    {
        $item = $this->item();

        $this->postJson(
            '/consultant/students/'.$this->student->id.'/schedule/items/'.$item->id.'/comments',
            ['comment_text' => 'آفرین، ادامه بده']
        )->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('item_comments', [
            'item_id' => $item->id,
            'tenant_id' => 1,
            'commenter_type' => 'user',
            'commenter_user_id' => 1,
            'comment_text' => 'آفرین، ادامه بده',
        ]);
    }

    public function test_reading_a_thread_reports_who_wrote_each_message(): void
    {
        $item = $this->item();

        DB::table('item_comments')->insert([
            [
                'tenant_id' => 1, 'item_id' => $item->id, 'commenter_type' => 'student',
                'commenter_user_id' => null, 'commenter_student_id' => $this->student->id,
                'comment_text' => 'مریض بودم', 'created_at' => now(),
            ],
            [
                'tenant_id' => 1, 'item_id' => $item->id, 'commenter_type' => 'user',
                'commenter_user_id' => 1, 'commenter_student_id' => null,
                'comment_text' => 'مهم نیست', 'created_at' => now(),
            ],
        ]);

        $response = $this->getJson(
            '/consultant/students/'.$this->student->id.'/schedule/items/'.$item->id.'/comments'
        );

        $response->assertOk();
        $this->assertSame('student', $response->json('0.author'));
        $this->assertSame('consultant', $response->json('1.author'));
    }

    public function test_an_empty_comment_is_rejected(): void
    {
        $item = $this->item();

        $this->postJson(
            '/consultant/students/'.$this->student->id.'/schedule/items/'.$item->id.'/comments',
            ['comment_text' => '']
        )->assertStatus(422)->assertJsonValidationErrors(['comment_text']);

        $this->assertSame(0, DB::table('item_comments')->count());
    }

    /* ------------------------------------------------------- isolation --- */

    public function test_consultant_cannot_write_notes_for_a_different_tenant_student(): void
    {
        $foreign = $this->foreignStudent;

        $this->putJson($this->notesUrl($foreign), [
            'week_start_date' => $this->weekStart(),
            'notes' => [0 => 'نباید ذخیره شود'],
        ])->assertNotFound();

        $this->assertSame(0, ScheduleWeekNote::query()->count());
    }

    public function test_consultant_cannot_comment_on_a_different_students_block(): void
    {
        // Same tenant, but the URL names a different student than the item's.
        $foreignItem = $this->item($this->otherStudent);

        $this->postJson(
            '/consultant/students/'.$this->student->id.'/schedule/items/'.$foreignItem->id.'/comments',
            ['comment_text' => 'دسترسی غیرمجاز']
        )->assertNotFound();

        $this->assertSame(0, DB::table('item_comments')->count());
    }

    public function test_notes_are_never_visible_across_tenants(): void
    {
        ScheduleWeekNote::create([
            'tenant_id' => 2,
            'student_id' => 3,
            'week_start_date' => $this->weekStart(),
            'day_index' => 0,
            'note_text' => 'یادداشت تننت دیگر',
        ]);

        $this->getJson($this->notesUrl().'?week_start_date='.$this->weekStart())
            ->assertOk()
            ->assertJsonPath('note_count', 0);
    }

    public function test_suggestions_do_not_leak_a_second_tenants_phrases(): void
    {
        $this->item($this->foreignStudent, ['title' => 'فقط تننت دو']);

        $response = $this->getJson('/consultant/students/'.$this->student->id.'/schedule/suggestions');

        $this->assertNotContains('فقط تننت دو', array_column($response->json('suggestions'), 'title'));
    }
}
