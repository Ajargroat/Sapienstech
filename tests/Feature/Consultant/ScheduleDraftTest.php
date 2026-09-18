<?php

namespace Tests\Feature\Consultant;

use App\Http\Middleware\ApplyPersonalTheme;
use App\Http\Middleware\EnsureUserDomain;
use App\Http\Middleware\IdentifyTenant;
use App\Models\ScheduleDraft;
use App\Models\ScheduleItem;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SiteConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ScheduleDraftTest extends TestCase
{
    private User $consultant;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // Never use the configured application DB: the legacy base schema is not migrated.
        config([
            'database.default' => 'schedule_draft_test',
            'database.connections.schedule_draft_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);

        foreach (['tenants', 'users', 'students'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                if ($tableName !== 'tenants') {
                    $table->foreignId('tenant_id')->constrained('tenants');
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
            $table->foreignId('item_id')->constrained('schedule_items');
            $table->text('comment_text');
        });

        (require database_path('migrations/2026_09_18_000001_create_schedule_drafts_table.php'))->up();

        DB::table('tenants')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert([
            ['id' => 1, 'tenant_id' => 1],
            ['id' => 2, 'tenant_id' => 1],
            ['id' => 3, 'tenant_id' => 2],
        ]);
        DB::table('students')->insert([
            ['id' => 1, 'tenant_id' => 1],
            ['id' => 2, 'tenant_id' => 1],
            ['id' => 3, 'tenant_id' => 2],
        ]);

        $this->app->instance('tenant', (new Tenant)->forceFill(['id' => 1]));
        $this->consultant = $this->user(1, 1);
        $this->student = Student::findOrFail(1);
        $this->actingAs($this->consultant);

        // Keep auth, route binding, FormRequests and the actual feature middleware.
        // Host/domain/theme resolution is independent of the draft API.
        $this->withoutMiddleware([IdentifyTenant::class, EnsureUserDomain::class, ApplyPersonalTheme::class]);
        $this->featureEnabled(true);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            DB::purge('schedule_draft_test');
        }

        parent::tearDown();
    }

    private function featureEnabled(bool $enabled): void
    {
        $site = Mockery::mock(SiteConfig::class);
        $site->shouldReceive('all')->andReturn(['features' => ['student_schedule' => $enabled]]);
        $this->app->instance(SiteConfig::class, $site);
    }

    private function user(int $id, int $tenantId): User
    {
        return (new User)->forceFill(['id' => $id, 'tenant_id' => $tenantId, 'role' => 'consultant_staff']);
    }

    private function url(?int $studentId = null): string
    {
        return '/consultant/students/'.($studentId ?? $this->student->id).'/schedule/drafts';
    }

    private function block(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Physics',
            'day_index' => 0,
            'start_time' => '09:00',
            'end_time' => '10:30',
            'color' => '#22c55e',
            'book_name' => 'Mechanics',
            'page_count' => 10,
            'test_count' => 20,
            'description' => 'Review chapter one',
            'link_url' => 'https://example.com/lesson',
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Weekly revision',
            'week_start_date' => '2026-09-16',
            'blocks' => [$this->block()],
        ], $overrides);
    }

    private function saveDraft(array $overrides = []): int
    {
        return $this->postJson($this->url(), $this->payload($overrides))
            ->assertCreated()->assertJsonPath('success', true)->json('draft.id');
    }

    public function test_multiple_named_drafts_are_private_payloads_and_listed_across_students(): void
    {
        $first = $this->saveDraft();
        $second = $this->saveDraft(['name' => 'Exam week']);

        $response = $this->getJson($this->url(2))->assertOk()->assertJsonCount(2, 'drafts');
        $response->assertJsonStructure(['drafts' => [['id', 'name', 'week_start_date', 'blocks', 'updated_at']]])
            ->assertJsonFragment(['id' => $first, 'name' => 'Weekly revision'])
            ->assertJsonFragment(['id' => $second, 'name' => 'Exam week'])
            ->assertJsonPath('drafts.0.week_start_date', '2026-09-12');
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'week_start_date', 'blocks', 'updated_at'],
            array_keys($response->json('drafts.0'))
        );
        $this->assertDatabaseCount('schedule_items', 0);
        $this->assertDatabaseHas('schedule_drafts', ['id' => $first, 'tenant_id' => 1, 'user_id' => 1]);
    }

    public function test_update_replaces_only_the_selected_draft_and_strips_unknown_fields(): void
    {
        $id = $this->saveDraft();
        $other = $this->saveDraft(['name' => 'Keep me']);
        $extra = [
            'id' => 777, 'tenant_id' => 2, 'student_id' => 3, 'user_id' => 3,
            'created_by_user_id' => 3, 'created_by_student_id' => 3,
            'is_completed' => true, 'completion_timestamp' => '2026-09-12 10:00:00',
            'comments' => [['comment_text' => 'Private']], 'bulk_action_id' => 100,
            'start_datetime' => '2020-01-01 00:00:00', 'week_start_date' => '2020-01-01',
        ];
        $payload = $this->payload([
            'name' => 'Updated', 'week_start_date' => '2026-10-01',
            'blocks' => [$this->block(['title' => 'Math', 'day_index' => 6]) + $extra],
        ]) + ['tenant_id' => 2, 'user_id' => 3, 'student_id' => 3, 'unknown' => true];

        $this->putJson($this->url(2)."/{$id}", $payload)->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('draft.name', 'Updated')
            ->assertJsonPath('draft.week_start_date', '2026-09-26')
            ->assertJsonPath('draft.blocks', [$this->block(['title' => 'Math', 'day_index' => 6])]);
        $draft = ScheduleDraft::findOrFail($id);
        $this->assertSame([$this->block(['title' => 'Math', 'day_index' => 6])], $draft->blocks);
        $this->assertDatabaseHas('schedule_drafts', ['id' => $id, 'tenant_id' => 1, 'user_id' => 1]);
        $this->assertDatabaseHas('schedule_drafts', ['id' => $other, 'name' => 'Keep me']);
        $this->assertDatabaseCount('schedule_items', 0);

        $created = $this->postJson($this->url(), $payload)->assertCreated()->json('draft');
        $this->assertSame($draft->blocks, $created['blocks']);
        $this->assertDatabaseHas('schedule_drafts', ['id' => $created['id'], 'tenant_id' => 1, 'user_id' => 1]);
    }

    public function test_other_users_and_tenants_cannot_list_update_or_apply_a_draft(): void
    {
        $id = $this->saveDraft();
        $original = ScheduleDraft::findOrFail($id)->getAttributes();

        foreach ([$this->user(2, 1), $this->user(3, 2)] as $user) {
            $this->app->instance('tenant', (new Tenant)->forceFill(['id' => $user->tenant_id]));
            $this->actingAs($user);
            $url = $this->url($user->tenant_id === 1 ? 1 : 3);
            $this->getJson($url)->assertOk()->assertExactJson(['drafts' => []]);
            $this->putJson($url."/{$id}", $this->payload())->assertNotFound();
            $this->postJson($url."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertNotFound();
        }

        $this->assertSame($original, ScheduleDraft::withoutGlobalScopes()->findOrFail($id)->getAttributes());
        $this->assertDatabaseCount('schedule_items', 0);
    }

    public function test_all_endpoints_reject_a_student_from_another_tenant(): void
    {
        $id = $this->saveDraft();
        $this->getJson($this->url(3))->assertNotFound();
        $this->postJson($this->url(3), $this->payload())->assertNotFound();
        $this->putJson($this->url(3)."/{$id}", $this->payload())->assertNotFound();
        $this->postJson($this->url(3)."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertNotFound();
    }

    public function test_controller_fails_closed_for_missing_tenant_or_mismatched_user(): void
    {
        $id = $this->saveDraft();
        $this->actingAs($this->user(3, 2));
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        $this->putJson($this->url()."/{$id}", $this->payload())->assertNotFound();
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertNotFound();

        $this->actingAs($this->consultant);
        $this->app->forgetInstance('tenant');
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertNotFound();
        $this->assertDatabaseCount('schedule_items', 0);
    }

    public function test_draft_endpoints_require_authentication_and_the_schedule_feature(): void
    {
        $id = $this->saveDraft();
        $this->featureEnabled(false);
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url(), $this->payload())->assertNotFound();
        $this->putJson($this->url()."/{$id}", $this->payload())->assertNotFound();
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertNotFound();

        $this->featureEnabled(true);
        $this->app['auth']->forgetGuards();
        $this->getJson($this->url())->assertUnauthorized();
        $this->postJson($this->url(), $this->payload())->assertUnauthorized();
        $this->putJson($this->url()."/{$id}", $this->payload())->assertUnauthorized();
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertUnauthorized();
    }

    public function test_apply_rebases_retains_resets_and_appends_without_overwriting_events(): void
    {
        $blocks = [$this->block(), $this->block(['title' => 'Friday', 'day_index' => 6, 'color' => null])];
        $id = $this->saveDraft(['blocks' => $blocks]);
        $draft = ScheduleDraft::findOrFail($id);
        // Even unexpected legacy payload keys must not become schedule item state.
        $draft->blocks = array_map(fn ($block) => $block + [
            'student_id' => 3, 'tenant_id' => 2, 'created_by_user_id' => 3,
            'created_by_student_id' => 3, 'is_completed' => true,
            'completion_timestamp' => '2026-09-12 10:30:00',
            'comments' => [['comment_text' => 'Do not copy']], 'item_type' => 'student_personal_block',
        ], $blocks);
        $draft->save();
        $originalDraft = $draft->getAttributes();
        $existing = ScheduleItem::create([
            'tenant_id' => 1, 'student_id' => 2, 'title' => 'Existing',
            'week_start_date' => '2026-09-26', 'start_datetime' => '2026-09-26 09:00:00',
            'end_datetime' => '2026-09-26 10:30:00', 'color' => '#ffffff',
            'item_type' => 'student_personal_block', 'created_by_type' => 'student',
            'created_by_student_id' => 2, 'is_completed' => true,
            'completion_timestamp' => '2026-09-26 10:30:00',
        ])->fresh();
        DB::table('item_comments')->insert(['item_id' => $existing->id, 'comment_text' => 'Keep comment']);

        $this->postJson($this->url(2)."/{$id}/apply", ['week_start_date' => '2026-09-30'])
            ->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame($existing->getAttributes(), $existing->fresh()->getAttributes());
        $this->assertSame($originalDraft, $draft->fresh()->getAttributes());
        $this->assertDatabaseCount('schedule_items', 3);
        $this->assertDatabaseCount('item_comments', 1);
        $this->assertSame('2026-09-26', ScheduleItem::where('title', 'Physics')->firstOrFail()->week_start_date->toDateString());
        $this->assertDatabaseHas('schedule_items', [
            'student_id' => 2, 'title' => 'Physics',
            'start_datetime' => '2026-09-26 09:00:00', 'end_datetime' => '2026-09-26 10:30:00',
            'tenant_id' => 1, 'created_by_user_id' => 1, 'created_by_student_id' => null,
            'item_type' => 'consultant_event', 'created_by_type' => 'user',
            'is_completed' => 0, 'completion_timestamp' => null,
            'book_name' => 'Mechanics', 'page_count' => 10, 'test_count' => 20,
            'description' => 'Review chapter one', 'link_url' => 'https://example.com/lesson',
        ]);
        $this->assertDatabaseHas('schedule_items', [
            'student_id' => 2, 'title' => 'Friday', 'start_datetime' => '2026-10-02 09:00:00',
            'end_datetime' => '2026-10-02 10:30:00', 'color' => '#3b82f6',
        ]);

        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-10-03'])->assertOk();
        $this->assertDatabaseHas('schedule_items', [
            'student_id' => 1, 'title' => 'Physics', 'start_datetime' => '2026-10-03 09:00:00',
        ]);
        $this->assertDatabaseCount('schedule_items', 5);
        $this->assertSame($originalDraft, $draft->fresh()->getAttributes());
    }

    public function test_empty_drafts_and_minimal_blocks_are_supported(): void
    {
        $id = $this->saveDraft(['blocks' => []]);
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertOk();
        $this->assertDatabaseCount('schedule_items', 0);
        $minimal = ['title' => 'Minimal', 'day_index' => 0, 'start_time' => '09:00', 'end_time' => '10:00'];
        $this->putJson($this->url()."/{$id}", $this->payload(['blocks' => [$minimal]]))->assertOk();
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])->assertOk();
        $this->assertDatabaseHas('schedule_items', ['title' => 'Minimal', 'color' => '#3b82f6']);
        $this->putJson($this->url()."/{$id}", $this->payload(['blocks' => []]))->assertOk()->assertJsonPath('draft.blocks', []);
        $this->assertDatabaseCount('schedule_items', 1);
    }

    #[DataProvider('invalidBlockValues')]
    public function test_invalid_nested_values_reject_the_entire_save_and_update(string $field, mixed $value): void
    {
        $id = $this->saveDraft();
        $original = ScheduleDraft::findOrFail($id)->getAttributes();
        $payload = $this->payload(['blocks' => [
            $this->block(['start_time' => '06:00', 'end_time' => '07:00']),
            $this->block([$field => $value]),
        ]]);
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors("blocks.1.{$field}");
        $this->putJson($this->url()."/{$id}", $payload)->assertUnprocessable()->assertJsonValidationErrors("blocks.1.{$field}");
        $this->assertSame($original, ScheduleDraft::findOrFail($id)->getAttributes());
        $this->assertDatabaseCount('schedule_drafts', 1);
        $this->assertDatabaseCount('schedule_items', 0);
    }

    public static function invalidBlockValues(): array
    {
        return [
            'end before matching start' => ['end_time', '08:00'],
            'equal times' => ['end_time', '09:00'],
            'bad clock' => ['start_time', '24:00'],
            'missing title' => ['title', ''],
            'long title' => ['title', str_repeat('a', 256)],
            'negative day' => ['day_index', -1],
            'day past week' => ['day_index', 7],
            'fractional day' => ['day_index', 1.5],
            'bad color' => ['color', str_repeat('a', 31)],
            'long book' => ['book_name', str_repeat('a', 256)],
            'negative pages' => ['page_count', -1],
            'large tests' => ['test_count', 100001],
            'long description' => ['description', str_repeat('a', 5001)],
            'bad link' => ['link_url', 'not-a-url'],
        ];
    }

    public function test_draft_envelope_validation_and_block_limit(): void
    {
        foreach ([
            ['name' => ''], ['name' => str_repeat('a', 256)],
            ['week_start_date' => '2026-02-30'], ['week_start_date' => 'bad'],
            ['blocks' => null], ['blocks' => 'not an array'],
            ['blocks' => [null]], ['blocks' => ['key' => $this->block()]],
            ['blocks' => array_fill(0, 501, $this->block())],
        ] as $overrides) {
            $this->postJson($this->url(), $this->payload($overrides))->assertUnprocessable();
        }
        $payload = $this->payload();
        unset($payload['blocks']);
        $this->postJson($this->url(), $payload)->assertUnprocessable()->assertJsonValidationErrors('blocks');
        $this->assertDatabaseCount('schedule_drafts', 0);
        $this->saveDraft(['blocks' => array_fill(0, 500, $this->block())]);
        $this->assertDatabaseCount('schedule_items', 0);
    }

    public function test_invalid_apply_date_or_stored_block_creates_no_items(): void
    {
        $id = $this->saveDraft();
        foreach ([[], ['week_start_date' => 'bad'], ['week_start_date' => '2026-02-30']] as $payload) {
            $this->postJson($this->url()."/{$id}/apply", $payload)->assertUnprocessable();
        }
        $draft = ScheduleDraft::findOrFail($id);
        $draft->update(['blocks' => [$this->block(), $this->block(['end_time' => '08:00'])]]);
        $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26'])
            ->assertUnprocessable()->assertJsonValidationErrors('blocks.1.end_time');
        $this->assertDatabaseCount('schedule_items', 0);
        $this->assertDatabaseCount('schedule_drafts', 1);
    }

    public function test_apply_rolls_back_earlier_blocks_if_a_later_insert_fails(): void
    {
        $id = $this->saveDraft(['blocks' => [$this->block(), $this->block(['day_index' => 1])]]);
        $original = ScheduleDraft::findOrFail($id)->getAttributes();
        ScheduleItem::query()->count();
        $dispatcher = ScheduleItem::getEventDispatcher();
        ScheduleItem::setEventDispatcher(clone $dispatcher);
        $attempts = 0;
        ScheduleItem::creating(function () use (&$attempts) {
            if (++$attempts === 2) {
                throw new RuntimeException('Simulated second insert failure');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->url()."/{$id}/apply", ['week_start_date' => '2026-09-26']);
            $this->fail('Expected the second insert to fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated second insert failure', $exception->getMessage());
        } finally {
            ScheduleItem::setEventDispatcher($dispatcher);
        }

        $this->assertSame(2, $attempts);
        $this->assertDatabaseCount('schedule_items', 0);
        $this->assertSame($original, ScheduleDraft::findOrFail($id)->getAttributes());
    }
}
