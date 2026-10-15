<?php

namespace Tests\Feature\Student;

use App\Http\Middleware\ApplyPersonalTheme;
use App\Http\Middleware\EnsureUserDomain;
use App\Http\Middleware\IdentifyTenant;
use App\Models\ItemComment;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\SiteConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * The student portal's half of the weekly planner: marking a block done and
 * commenting on it. Driven exactly as the browser drives it (JSON + the
 * student guard), because the failure we are reproducing is an HTTP one.
 */
class StudentSchedulePortalTest extends TestCase
{
    private Student $student;

    private Student $otherStudent;

    private Student $foreignStudent;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'student_portal_test',
            'database.connections.student_portal_test' => [
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
        $this->student = Student::findOrFail(1);
        $this->otherStudent = Student::findOrFail(2);
        $this->foreignStudent = Student::withoutGlobalScopes()->findOrFail(3);

        $this->withoutMiddleware([IdentifyTenant::class, EnsureUserDomain::class, ApplyPersonalTheme::class]);

        $site = Mockery::mock(SiteConfig::class);
        $site->shouldReceive('all')->andReturn(['features' => ['student_schedule' => true]]);
        $this->app->instance(SiteConfig::class, $site);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            DB::purge('student_portal_test');
        }

        parent::tearDown();
    }

    private function completionUrl($item): string
    {
        return '/student/schedule/items/'.($item instanceof ScheduleItem ? $item->id : $item).'/completion';
    }

    private function commentsUrl($item): string
    {
        return '/student/schedule/items/'.($item instanceof ScheduleItem ? $item->id : $item).'/comments';
    }

    private function item(Student $student, array $attributes = []): ScheduleItem
    {
        return ScheduleItem::create(array_replace([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'title' => 'مطالعه ریاضی دهم',
            'start_datetime' => now()->startOfWeek()->toDateString().' 09:00:00',
            'end_datetime' => now()->startOfWeek()->toDateString().' 10:00:00',
            'color' => '#3b82f6',
            'item_type' => 'consultant_event',
            'created_by_type' => 'user',
            'created_by_user_id' => 1,
        ], $attributes));
    }

    public function test_student_can_mark_a_block_done(): void
    {
        $item = $this->item($this->student);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->completionUrl($item), ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_completed', true);

        $this->assertDatabaseHas('schedule_items', ['id' => $item->id, 'is_completed' => 1]);
    }

    public function test_student_can_unmark_a_block(): void
    {
        $item = $this->item($this->student, ['is_completed' => true]);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->completionUrl($item), ['is_completed' => false])
            ->assertOk()
            ->assertJsonPath('is_completed', false);

        $this->assertDatabaseHas('schedule_items', ['id' => $item->id, 'is_completed' => 0]);
    }

    public function test_student_can_comment_on_a_block(): void
    {
        $item = $this->item($this->student);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->commentsUrl($item), ['comment_text' => 'مریض بودم'])->assertStatus(201);

        $this->assertDatabaseHas('item_comments', [
            'item_id' => $item->id,
            'commenter_type' => 'student',
            'commenter_student_id' => $this->student->id,
        ]);
    }

    public function test_student_reads_a_thread_oldest_first(): void
    {
        $item = $this->item($this->student);

        ItemComment::create([
            'tenant_id' => 1, 'item_id' => $item->id, 'commenter_type' => 'user',
            'commenter_user_id' => 1, 'comment_text' => 'دقت کن',
        ]);
        ItemComment::create([
            'tenant_id' => 1, 'item_id' => $item->id, 'commenter_type' => 'student',
            'commenter_student_id' => $this->student->id, 'comment_text' => 'چشم',
        ]);

        $this->actingAs($this->student, 'student');

        $response = $this->getJson($this->commentsUrl($item))->assertOk();

        $this->assertSame('consultant', $response->json('0.author'));
        $this->assertSame('student', $response->json('1.author'));
    }

    public function test_empty_comment_is_rejected(): void
    {
        $item = $this->item($this->student);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->commentsUrl($item), ['comment_text' => ''])->assertStatus(422);
    }

    public function test_another_students_block_is_not_reachable(): void
    {
        $item = $this->item($this->otherStudent);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->completionUrl($item), ['is_completed' => true])->assertStatus(404);
        $this->postJson($this->commentsUrl($item), ['comment_text' => 'سلام'])->assertStatus(404);
        $this->getJson($this->commentsUrl($item))->assertStatus(404);

        $this->assertDatabaseHas('schedule_items', ['id' => $item->id, 'is_completed' => 0]);
        $this->assertSame(0, ItemComment::query()->count());
    }

    public function test_a_foreign_tenants_block_is_not_reachable(): void
    {
        $item = $this->item($this->foreignStudent);

        $this->actingAs($this->student, 'student');

        $this->postJson($this->completionUrl($item), ['is_completed' => true])->assertStatus(404);
        $this->assertDatabaseHas('schedule_items', ['id' => $item->id, 'is_completed' => 0]);
    }
}
