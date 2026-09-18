<?php

namespace Tests\Feature;

use App\Http\Controllers\Consultant\Bulk\BulkHistoryController;
use App\Models\BulkAction;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HierarchyBulkHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never migrate or transact on the connection from the developer's .env.
        config(['database.default' => 'hierarchy_bulk_history_test', 'database.connections.hierarchy_bulk_history_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        DB::purge('hierarchy_bulk_history_test');
        Event::fake();

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('active');
            $table->string('hierarchy_type')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamps();
        });
        foreach (['users', 'students'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                if ($name === 'users') {
                    $table->string('role');
                }
                $table->timestamps();
            });
        }
        Schema::create('student_staff', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('user_id');
        });
        foreach (['classroom_student' => 'student_id', 'classroom_teacher' => 'user_id'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($key) {
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('classroom_id');
                $table->unsignedBigInteger($key);
            });
        }
        Schema::create('bulk_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('kind');
            $table->text('summary')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });
        foreach (['student_assigned_quizzes', 'schedule_items'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('bulk_action_id');
                $table->timestamps();
            });
        }
        Schema::create('student_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('assignment_id');
        });

        // Exercise controller and route binding without unrelated host/layout dependencies.
        $this->withoutMiddleware(\App\Http\Middleware\IdentifyTenant::class);
        Route::middleware(['web', 'auth:web'])->group(function () {
            Route::get('/_bulk-history', [BulkHistoryController::class, 'index']);
            Route::delete('/_bulk-history/{action}', [BulkHistoryController::class, 'revert']);
        });
        $this->withHeader('Accept', 'application/json');
        
        View::partialMock()->shouldReceive('make')->andReturnUsing(function ($name, $data = []) {
            $view = Mockery::mock(\Illuminate\View\View::class);
            $view->shouldReceive('render')->andReturn('Bulk history fixture');
            $view->shouldReceive('getData', 'gatherData')->andReturn($data);
            $view->shouldReceive('name')->andReturn($name);

            return $view;
        });
    }

    protected function tearDown(): void
    {
        DB::purge('hierarchy_bulk_history_test');
        parent::tearDown();
    }

    private function academy(?string $type = 'school'): Tenant
    {
        return Tenant::create(['name' => 'Academy', 'slug' => uniqid('academy'), 'hierarchy_type' => $type]);
    }

    private function staff(Tenant $tenant, string $role = User::ROLE_CONSULTANT_STAFF): User
    {
        return User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'role' => $role]);
    }

    private function login(User $user, Tenant $tenant): void
    {
        $this->app->instance('tenant', $tenant);
        $this->actingAs($user, 'web');
    }

    private function action(User $user, string $kind): BulkAction
    {
        return BulkAction::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'kind' => $kind, 'summary' => ['title' => 'Private historical summary'], 'affected_count' => 1]);
    }

    private function target(BulkAction $action, ?User $assignedTo = null): int
    {
        $student = Student::create(['tenant_id' => $action->tenant_id, 'name' => 'Pupil']);
        if ($assignedTo) {
            $assignedTo->students()->attach($student->id, ['tenant_id' => $action->tenant_id]);
        }
        DB::table($this->table($action->kind))->insert(['tenant_id' => $action->tenant_id,
            'student_id' => $student->id, 'bulk_action_id' => $action->id]);

        return $student->id;
    }

    private function table(string $kind): string
    {
        return $kind === BulkAction::KIND_EXAM ? 'student_assigned_quizzes' : 'schedule_items';
    }

    public static function kinds(): array
    {
        return [[BulkAction::KIND_EXAM], [BulkAction::KIND_SCHEDULE]];
    }

    #[DataProvider('kinds')]
    public function test_staff_history_and_revert_are_limited_to_own_actions(string $kind): void
    {
        $tenant = $this->academy();
        $staff = $this->staff($tenant);
        $own = $this->action($staff, $kind);
        $other = $this->action($this->staff($tenant), $kind);
        $this->target($other, $staff);
        $this->login($staff, $tenant);

        $this->get('/_bulk-history')->assertOk()->assertViewHas('actions',
            fn ($actions) => $actions->pluck('id')->all() === [$own->id]);
        $this->delete('/_bulk-history/'.$other->id)->assertForbidden();
        $this->assertDatabaseHas($this->table($kind), ['bulk_action_id' => $other->id]);
        $this->assertNull($other->fresh()->reverted_at);
    }

    #[DataProvider('kinds')]
    public function test_staff_can_revert_own_assigned_students_only(string $kind): void
    {
        $tenant = $this->academy();
        $staff = $this->staff($tenant);
        $action = $this->action($staff, $kind);
        $this->target($action, $staff);
        $untouched = $this->action($staff, $kind);
        $this->target($untouched, $staff);
        $this->login($staff, $tenant);

        $this->delete('/_bulk-history/'.$action->id)->assertRedirect();
        $this->assertDatabaseMissing($this->table($kind), ['bulk_action_id' => $action->id]);
        $this->assertNotNull($action->fresh()->reverted_at);
        $this->assertDatabaseHas($this->table($kind), ['bulk_action_id' => $untouched->id]);
        $this->delete('/_bulk-history/'.$action->id)->assertStatus(422);
    }

    #[DataProvider('kinds')]
    public function test_revoking_one_student_rejects_the_entire_revert(string $kind): void
    {
        $tenant = $this->academy();
        $staff = $this->staff($tenant);
        $action = $this->action($staff, $kind);
        $this->target($action, $staff);
        $revoked = $this->target($action, $staff);
        $staff->students()->detach($revoked);
        $this->login($staff, $tenant);

        $this->delete('/_bulk-history/'.$action->id)->assertForbidden();
        $this->assertSame(2, DB::table($this->table($kind))->where('bulk_action_id', $action->id)->count());
        $this->assertNull($action->fresh()->reverted_at);
    }

    #[DataProvider('kinds')]
    public function test_owner_can_see_and_revert_other_staff_actions_without_roster_links(string $kind): void
    {
        $tenant = $this->academy();
        $owner = $this->staff($tenant, User::ROLE_TENANT_ADMIN);
        $tenant->update(['owner_user_id' => $owner->id]);
        $action = $this->action($this->staff($tenant), $kind);
        $this->target($action);
        $foreign = $this->action($this->staff($this->academy()), $kind);
        $this->login($owner, $tenant);

        $this->get('/_bulk-history')->assertOk()->assertViewHas('actions',
            fn ($actions) => $actions->pluck('id')->all() === [$action->id]);
        $this->delete('/_bulk-history/'.$foreign->id)->assertNotFound();
        $this->delete('/_bulk-history/'.$action->id)->assertRedirect();
        $this->assertDatabaseMissing($this->table($kind), ['bulk_action_id' => $action->id]);
        $this->assertNotNull($action->fresh()->reverted_at);
        $this->assertDatabaseHas('bulk_actions', ['id' => $foreign->id, 'reverted_at' => null]);
    }

    #[DataProvider('kinds')]
    public function test_legacy_tenant_keeps_shared_history_and_revert(string $kind): void
    {
        $tenant = $this->academy(null);
        $staff = $this->staff($tenant);
        $action = $this->action($this->staff($tenant), $kind);
        $this->target($action);
        $this->login($staff, $tenant);

        $this->get('/_bulk-history')->assertOk()->assertViewHas('actions',
            fn ($actions) => $actions->pluck('id')->all() === [$action->id]);
        $this->delete('/_bulk-history/'.$action->id)->assertRedirect();
        $this->assertDatabaseMissing($this->table($kind), ['bulk_action_id' => $action->id]);
        $this->assertNotNull($action->fresh()->reverted_at);
    }

    public function test_started_exam_still_blocks_revert(): void
    {
        $tenant = $this->academy();
        $staff = $this->staff($tenant);
        $action = $this->action($staff, BulkAction::KIND_EXAM);
        $this->target($action, $staff);
        DB::table('student_test_attempts')->insert(['tenant_id' => $tenant->id,
            'assignment_id' => DB::table('student_assigned_quizzes')->value('id')]);
        $this->login($staff, $tenant);

        $this->delete('/_bulk-history/'.$action->id)->assertStatus(422);
        $this->assertDatabaseHas('student_assigned_quizzes', ['bulk_action_id' => $action->id]);
        $this->assertNull($action->fresh()->reverted_at);
    }
}
