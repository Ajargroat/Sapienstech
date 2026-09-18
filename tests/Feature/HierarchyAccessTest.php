<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Domain;
use App\Models\Student;
use App\Support\BulkSelection;
use App\Support\SettingsTabs;
use App\Support\StudentFilter;
use Database\Seeders\HierarchyDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SiteConfig;
use App\Support\StudentAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HierarchyAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never migrate or transact on the connection from the developer's .env.
        config(['database.default' => 'hierarchy_access_test', 'database.connections.hierarchy_access_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'session.driver' => 'array', 'tenancy.fallback_domains' => [], 'hashing.bcrypt.rounds' => 4]);
        DB::purge('hierarchy_access_test');
        Event::fake();
        $site = $this->mock(SiteConfig::class);
                $site->shouldReceive('personalize')->andReturnSelf();
                $site->shouldReceive('all')->andReturn(['features' => [
            'teacher_panel' => true, 'student_profile' => true, 'settings_profile' => true,
            'settings_chat' => true, 'theme_studio' => true, 'blog_management' => true,
        ]]);
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamps();
        });
        foreach (['users', 'students'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('domain_id')->nullable();
                foreach (['name', 'email', 'password', 'avatar', 'bio', 'preferences'] as $column) {
                    $table->text($column)->nullable();
                }
                $table->rememberToken();
                if ($name === 'users') {
                    $table->string('role');
                } else {
                    foreach (['grade', 'gender', 'major'] as $column) {
                        $table->string($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_09_18_000001_create_tenant_hierarchy.php'))->up();
                Schema::create('domains', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id');
                    $table->string('domain')->unique();
                    $table->boolean('is_primary')->default(true);
                    $table->timestamp('verified_at')->nullable();
                    $table->timestamps();
                });
                Schema::create('website_configs', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id');
                    $table->text('layout_config')->nullable();
                    $table->timestamps();
                });
                Schema::create('schedule_items', function (Blueprint $table) {
                    $table->id();
                    foreach (['tenant_id', 'student_id', 'created_by_user_id', 'created_by_student_id', 'page_count', 'test_count'] as $column) {
                        $table->unsignedBigInteger($column)->nullable();
                    }
                    foreach (['title', 'description', 'week_start_date', 'start_datetime', 'end_datetime', 'completion_timestamp', 'color', 'item_type', 'created_by_type', 'book_name'] as $column) {
                        $table->text($column)->nullable();
                    }
                    $table->boolean('is_completed')->default(false);
                    $table->timestamps();
                });
                foreach (['assignments', 'lesson_materials'] as $name) {
                    Schema::create($name, function (Blueprint $table) {
                        $table->id();
                        $table->unsignedBigInteger('tenant_id');
                        $table->unsignedBigInteger('teacher_id');
                        foreach (['grade', 'title', 'subject', 'file_name'] as $column) {
                            $table->string($column)->nullable();
                        }
                        $table->timestamps();
                    });
                }
                Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::purge('hierarchy_access_test');
        parent::tearDown();
    }

    private function academy(string $slug = 'school', string $type = 'school'): Tenant
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'hierarchy_type' => $type]);
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $slug.'.hierarchy.test']);

        return $tenant;
    }

    private function staff(Tenant $tenant, string $role = User::ROLE_TEACHER): User
    {
        return User::create(['tenant_id' => $tenant->id, 'name' => $role, 'role' => $role,
            'email' => $role.$tenant->id.'@hierarchy.test', 'password' => 'HierarchyTest!2026']);
    }

    private function pupil(Tenant $tenant, string $name = 'Pupil'): Student
    {
        return Student::create(['tenant_id' => $tenant->id, 'name' => $name, 'grade' => 'هفتم']);
    }

    private function classroom(Tenant $tenant, User $teacher, Student $student): Classroom
    {
        $classroom = Classroom::create(['tenant_id' => $tenant->id, 'grade' => 'هفتم', 'name' => 'A']);
        $classroom->teachers()->attach($teacher->id, ['tenant_id' => $tenant->id, 'subject' => 'ریاضی']);
        $classroom->students()->attach($student->id, ['tenant_id' => $tenant->id]);

        return $classroom;
    }

    private function assign(Tenant $tenant, User $staff, Student $student): void
    {
        $staff->students()->attach($student->id, ['tenant_id' => $tenant->id]);
    }

    private function url(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->slug.'.hierarchy.test'.$path;
    }

    private function mockViews(): void
    {
        // JSON error responses avoid rendering error layouts against the minimal schema.
        $this->withHeader('Accept', 'application/json');
        // Keep controllers, route binding and all middleware real; omit layout DB dependencies.
        View::partialMock()->shouldReceive('make')->andReturnUsing(function ($name, $data = []) {
            $view = Mockery::mock(\Illuminate\View\View::class);
            $view->shouldReceive('render')->andReturn('Hierarchy fixture view');
            $view->shouldReceive('getData', 'gatherData')->andReturn($data);
            $view->shouldReceive('name')->andReturn($name);

            return $view;
        });
    }

    public function test_teacher_cannot_read_unrelated_same_tenant_student(): void
    {
        $tenant = Tenant::create(['name' => 'School', 'slug' => 'school', 'hierarchy_type' => 'school']);
        app()->instance('tenant', $tenant);
        $teacher = User::create(['tenant_id' => $tenant->id, 'name' => 'Teacher', 'role' => User::ROLE_TEACHER]);
        $student = Student::create(['tenant_id' => $tenant->id, 'name' => 'Unrelated']);
        $this->actingAs($teacher);

        $this->assertFalse(StudentAccess::allows($teacher, $student));
        $this->assertNull(Student::find($student->id));
    }

    #[DataProvider('restrictedRoles')]
    public function test_settings_tabs_hide_owner_controls_from_hierarchy_staff(string $role, string $type): void
    {
        $tenant = $this->academy('academy', $type);
        app()->instance('tenant', $tenant);
        $this->actingAs($this->staff($tenant, $role));

        $this->assertSame([], SettingsTabs::visible('consultant'));
    }

    public function test_hierarchy_staff_cannot_write_theme_settings_directly(): void
    {
        $tenant = $this->academy('write-boundary', 'consultancy');
        $staff = $this->staff($tenant, User::ROLE_CONSULTANT_STAFF);
        $this->actingAs($staff);

        $this->postJson($this->url($tenant, '/consultant/settings/appearance'), [
            'scope' => 'everyone',
            'values' => ['theme.colors.primary' => '#123456'],
        ])->assertNotFound();

        $this->assertSame(0, DB::table('website_configs')->count());
        $this->assertNull($staff->fresh()->preferences);
    }

    public static function foreignLinkCases(): array
    {
        return [
            'student pivot foreign classroom' => ['classroom_student', 'classroom_id'],
            'student pivot foreign student' => ['classroom_student', 'student_id'],
            'teacher pivot foreign classroom' => ['classroom_teacher', 'classroom_id'],
            'teacher pivot foreign teacher' => ['classroom_teacher', 'user_id'],
            'staff pivot foreign student' => ['student_staff', 'student_id'],
            'staff pivot foreign staff' => ['student_staff', 'user_id'],
        ];
    }

    #[DataProvider('foreignLinkCases')]
    public function test_migration_rejects_cross_tenant_links_at_both_ends(string $table, string $foreignKey): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $local = $this->academy();
        $foreign = $this->academy('foreign');
        $ids = [];
        foreach ([$local, $foreign] as $tenant) {
            $ids[$tenant->id] = [
                'classroom_id' => Classroom::create(['tenant_id' => $tenant->id, 'grade' => '7', 'name' => 'A'])->id,
                'student_id' => $this->pupil($tenant)->id,
                'user_id' => $this->staff($tenant)->id,
            ];
        }
        $keys = $table === 'classroom_student' ? ['classroom_id', 'student_id']
            : ($table === 'classroom_teacher' ? ['classroom_id', 'user_id'] : ['student_id', 'user_id']);
        $row = ['tenant_id' => $local->id] + array_intersect_key($ids[$local->id], array_flip($keys));
        if ($table === 'classroom_teacher') {
            $row['subject'] = 'Math';
        }
        DB::table($table)->insert($row);
        $row[$foreignKey] = $ids[$foreign->id][$foreignKey];
        try {
            DB::table($table)->insert($row);
            $this->fail('A cross-tenant relationship was accepted.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }
        $this->assertSame(1, DB::table($table)->count());
    }

    public function test_shared_classroom_and_explicit_actor_checks_do_not_inherit_logged_in_roster(): void
    {
        $tenant = $this->academy();
        app()->instance('tenant', $tenant);
        $teacher = $this->staff($tenant);
        $peer = $this->staff($tenant);
        $unrelatedTeacher = $this->staff($tenant);
        $student = $this->pupil($tenant);
        $unrelated = $this->pupil($tenant, 'Unrelated');
        $classroom = $this->classroom($tenant, $teacher, $student);
        $classroom->teachers()->attach($peer->id, ['tenant_id' => $tenant->id, 'subject' => 'Science']);
        $this->actingAs($unrelatedTeacher);
        foreach ([$teacher, $peer] as $actor) {
            $this->assertTrue(StudentAccess::allows($actor, $student));
            $this->assertFalse(StudentAccess::allows($actor, $unrelated));
            $this->assertSame([$student->id], StudentAccess::scope(Student::withoutGlobalScope('staff_access'), $actor)->pluck('id')->all());
        }
        $this->actingAs($teacher);
        $this->assertFalse(StudentAccess::allows($unrelatedTeacher, $student));
        $this->assertSame([$student->id], Student::pluck('id')->all());
        $this->assertSame([$classroom->id], $teacher->classrooms()->pluck('classrooms.id')->all());
        $this->assertSame([$classroom->id], $student->classrooms()->pluck('classrooms.id')->all());
        $this->assertSame(1, $tenant->classrooms()->count());
        $classroom->teachers()->detach($teacher->id);
        $this->assertFalse(StudentAccess::allows($teacher, $student));
        $this->assertTrue(StudentAccess::allows($peer, $student));
    }

    public function test_consultant_shared_assignments_allow_only_assigned_students(): void
    {
        $tenant = $this->academy('consultancy', 'consultancy');
        app()->instance('tenant', $tenant);
        $first = $this->staff($tenant, User::ROLE_CONSULTANT_STAFF);
        $second = $this->staff($tenant, User::ROLE_CONSULTANT_STAFF);
        $shared = $this->pupil($tenant, 'Shared');
        $exclusive = $this->pupil($tenant, 'Exclusive');
        $unassigned = $this->pupil($tenant, 'Unassigned');
        $this->assign($tenant, $first, $shared);
        $this->assign($tenant, $second, $shared);
        $this->assign($tenant, $first, $exclusive);
        foreach ([$first, $second] as $actor) {
            $this->actingAs($actor);
            $this->assertTrue(StudentAccess::allows($actor, $shared));
            $this->assertFalse(StudentAccess::allows($actor, $unassigned));
        }
        $this->assertFalse(StudentAccess::allows($second, $exclusive));
        $this->assertSame([$shared->id], $second->students()->pluck('students.id')->all());
        $this->assertCount(2, $shared->staff()->get());
        $this->assertSame(2, $tenant->consultants()->count());
        $this->mockViews();
        $this->get($this->url($tenant, '/consultant/students/'.$shared->id))->assertOk()->assertViewHas('student', fn ($row) => $row->id === $shared->id);
        $this->get($this->url($tenant, '/consultant/students/'.$unassigned->id))->assertNotFound();
        $this->get($this->url($tenant, '/consultant/students/'.$exclusive->id))->assertNotFound();
    }

    public function test_search_or_conditions_and_bulk_selection_keep_the_roster_boundary(): void
    {
        $tenant = $this->academy();
        $foreign = $this->academy('foreign');
        app()->instance('tenant', $tenant);
        $teacher = $this->staff($tenant);
        $byName = $this->pupil($tenant, 'Needle Name');
        $byEmail = $this->pupil($tenant, 'Email Match');
        $byEmail->update(['email' => 'needle@hierarchy.test']);
        $other = $this->pupil($tenant, 'Other');
        $hidden = $this->pupil($tenant, 'Needle Hidden');
        $hidden->update(['email' => 'needle-hidden@hierarchy.test']);
        $outside = $this->pupil($foreign, 'Needle Foreign');
        $outside->update(['email' => 'needle-foreign@hierarchy.test']);
        $classroom = $this->classroom($tenant, $teacher, $byName);
        $classroom->students()->attach([$byEmail->id => ['tenant_id' => $tenant->id], $other->id => ['tenant_id' => $tenant->id]]);
        $this->actingAs($teacher);
        $expected = [$byName->id, $byEmail->id];
        $filters = StudentFilter::fromRequest(Request::create('/', 'GET', ['search' => 'needle']));
        $this->assertEqualsCanonicalizing($expected, StudentFilter::ids($filters));
        $this->assertEqualsCanonicalizing($expected, Student::where('name', 'like', '%Needle%')->orWhere('email', 'like', '%needle%')->pluck('id')->all());
        $this->assertEqualsCanonicalizing($expected, BulkSelection::resolve(Request::create('/', 'POST', [
            'student_ids' => [$byName->id, $byEmail->id, $hidden->id, $outside->id, $byName->id, 999999],
        ])));
        $this->assertEqualsCanonicalizing($expected, BulkSelection::resolve(Request::create('/', 'POST', [
            'select_all' => true, 'search' => 'needle', 'student_ids' => [$hidden->id, $outside->id],
        ])));
        $this->assertEqualsCanonicalizing([...$expected, $other->id], BulkSelection::resolve(Request::create('/', 'POST', ['select_all' => true])));
        $this->mockViews();
        $this->get($this->url($tenant, '/teacher/students?search=needle'))->assertOk()
            ->assertViewHas('students', fn ($rows) => $rows->total() === 2 && $rows->pluck('id')->sort()->values()->all() === collect($expected)->sort()->values()->all());
    }

    public function test_teacher_student_http_binding_rejects_foreign_and_unrelated_ids(): void
    {
        $tenant = $this->academy();
        $foreign = $this->academy('foreign');
        $teacher = $this->staff($tenant);
        $allowed = $this->pupil($tenant);
        $unrelated = $this->pupil($tenant, 'Unrelated');
        $outside = $this->pupil($foreign, 'Foreign');
        $this->classroom($tenant, $teacher, $allowed);
        $this->mockViews();
        // Foreign is deliberately the first HTTP request: no pre-bound tenant can mask middleware ordering.
        $this->actingAs($teacher)->get($this->url($tenant, '/teacher/students/'.$outside->id))->assertNotFound();
        $this->get($this->url($tenant, '/teacher/students/'.$unrelated->id))->assertNotFound();
        $this->get($this->url($tenant, '/teacher/students/'.$allowed->id))->assertOk()
            ->assertViewHas('student', fn ($row) => $row->id === $allowed->id);
    }

    public function test_teacher_cannot_use_consultant_student_route_even_for_own_roster(): void
    {
        $tenant = $this->academy();
        $teacher = $this->staff($tenant);
        $student = $this->pupil($tenant);
        $this->classroom($tenant, $teacher, $student);
        $this->mockViews();
        $this->actingAs($teacher)->get($this->url($tenant, '/consultant/students/'.$student->id))->assertNotFound();
    }

    public function test_owner_can_read_all_own_students_but_not_another_tenant(): void
    {
        $tenant = $this->academy();
        $foreign = $this->academy('foreign');
        $owner = $this->staff($tenant, User::ROLE_TENANT_ADMIN);
        $tenant->update(['owner_user_id' => $owner->id]);
        $students = [$this->pupil($tenant, 'First'), $this->pupil($tenant, 'Second')];
        $outside = $this->pupil($foreign);
        $this->assertTrue($owner->isTenantOwner());
        $this->mockViews();
        $this->actingAs($owner);
        foreach ($students as $student) {
            $this->assertTrue(StudentAccess::allows($owner, $student));
            $this->get($this->url($tenant, '/consultant/students/'.$student->id))->assertOk()
                ->assertViewHas('student', fn ($row) => $row->id === $student->id);
        }
        $this->assertSame(2, Student::count());
        $this->assertFalse(StudentAccess::allows($owner, $outside));
        $this->get($this->url($tenant, '/consultant/students/'.$outside->id))->assertNotFound();
    }

    public function test_known_hosts_resolve_and_unknown_host_is_not_a_fallback(): void
    {
        $first = $this->academy();
        $second = $this->academy('second', 'consultancy');
        $this->mockViews();
        foreach ([$first, $second] as $tenant) {
            $this->get($this->url($tenant, '/login'))->assertOk();
            $this->assertSame($tenant->id, tenant()->id);
            $this->assertSame($tenant->id, domain()->tenant_id);
        }
        $this->get('http://unknown.hierarchy.test/login')->assertNotFound();
    }

    public function test_foreign_host_rejects_valid_credentials_and_own_host_redirects_teacher(): void
    {
        $tenant = $this->academy();
        $foreign = $this->academy('foreign');
        $teacher = $this->staff($tenant);
        $credentials = ['email' => $teacher->email, 'password' => 'HierarchyTest!2026'];
        $this->post($this->url($foreign, '/login'), $credentials)->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->post($this->url($tenant, '/login'), $credentials)->assertRedirect($this->url($tenant, '/teacher/dashboard'));
        $this->assertAuthenticatedAs($teacher, 'web');
    }

    public static function restrictedRoles(): array
    {
        return ['teacher' => [User::ROLE_TEACHER, 'school'], 'consultant' => [User::ROLE_CONSULTANT_STAFF, 'consultancy']];
    }

    public static function restrictedRoutes(): array
    {
        $cases = [];
        foreach (self::restrictedRoles() as $name => [$role, $type]) {
            foreach (['/consultant/settings/profile', '/consultant/settings/profile?tab=appearance', '/consultant/settings/appearance', '/consultant/blog/create'] as $path) {
                $cases[$name.' '.$path] = [$role, $type, $path];
            }
        }

        return $cases;
    }

    #[DataProvider('restrictedRoutes')]
    public function test_hierarchy_staff_cannot_open_settings_or_blog(string $role, string $type, string $path): void
    {
        $tenant = $this->academy('academy', $type);
        $this->mockViews();
        $response = $this->actingAs($this->staff($tenant, $role))->get($this->url($tenant, $path));
        $this->assertContains($response->status(), [403, 404], $path.' must be denied for '.$role);
    }

    public function test_owner_settings_route_and_settings_tabs_remain_available(): void
    {
        $tenant = $this->academy();
        $owner = $this->staff($tenant, User::ROLE_TENANT_ADMIN);
        $tenant->update(['owner_user_id' => $owner->id]);
        $this->mockViews();
        $this->actingAs($owner)->get($this->url($tenant, '/consultant/settings/profile'))->assertOk()
            ->assertViewHas('activeTab', 'profile');
        $this->assertContains('appearance', array_column(SettingsTabs::visible('consultant'), 'key'));
        $this->get($this->url($tenant, '/consultant/settings/profile?tab=appearance'))->assertOk()
            ->assertViewHas('activeTab', 'appearance');
        $this->get($this->url($tenant, '/consultant/blog/create'))->assertOk();
        $this->get($this->url($tenant, '/consultant/settings/appearance'))
            ->assertRedirect($this->url($tenant, '/consultant/settings/profile?tab=appearance'));
    }

    public static function scienceSubjects(): array
    {
        return [
            'physics' => ['/فیزیک|physics/iu'],
            'biology' => ['/زیست|biology/iu'],
            'chemistry' => ['/شیمی|chemistry/iu'],
        ];
    }

    #[DataProvider('scienceSubjects')]
    public function test_demo_seeder_includes_required_science_subject_accounts(string $subjectPattern): void
    {
        (new HierarchyDemoSeeder)->run();
        $moein = Tenant::where('slug', 'moein')->firstOrFail();
        $this->assertTrue($moein->teachers()->get()->contains(
            fn ($teacher) => preg_match($subjectPattern, $teacher->preferences['demo']['subject'] ?? '') === 1
        ), 'Missing demo teacher subject matching '.$subjectPattern);
    }

    public function test_demo_seeder_is_repeatable_and_preserves_existing_owner_and_passwords(): void
    {
        $moein = Tenant::create(['name' => 'Existing Moein', 'slug' => 'moein']);
        $domain = Domain::create(['tenant_id' => $moein->id, 'domain' => 'existing-moein.hierarchy.test', 'is_primary' => true]);
        $owner = $this->staff($moein, User::ROLE_TENANT_ADMIN);
        $moein->update(['owner_user_id' => $owner->id]);
        $existing = User::create(['tenant_id' => $moein->id, 'domain_id' => $domain->id, 'name' => 'Existing Math',
            'email' => 'hierarchy.math@moein.test', 'role' => User::ROLE_TEACHER, 'password' => 'KeepThisPassword!2026']);
        $student = Student::create(['tenant_id' => $moein->id, 'domain_id' => $domain->id, 'name' => 'Existing Student',
            'email' => 'hierarchy.student01@moein.test', 'grade' => 'هفتم', 'password' => 'KeepStudentPassword!2026']);
        $passwords = [$owner->password, $existing->password, $student->password];
        $seeder = new HierarchyDemoSeeder;
        $seeder->run();
        $tables = ['tenants', 'domains', 'users', 'students', 'classrooms', 'classroom_student', 'classroom_teacher', 'student_staff', 'schedule_items'];
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }
        $seeder->run();
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' must not grow on rerun');
        }
        $this->assertSame($owner->id, $moein->fresh()->owner_user_id);
        $this->assertSame($passwords, [$owner->fresh()->password, $existing->fresh()->password, $student->fresh()->password]);
        $this->assertTrue(Hash::check('KeepThisPassword!2026', $existing->fresh()->password));
        $this->assertSame('Existing Math', $existing->fresh()->name);
        $this->assertSame('Existing Student', $student->fresh()->name);
        $this->assertSame('existing-moein.hierarchy.test', $domain->fresh()->domain);
        $salam = Tenant::where('slug', 'salam')->firstOrFail();
        $this->assertSame('school', $moein->fresh()->hierarchy_type);
        $this->assertSame('consultancy', $salam->hierarchy_type);
        $this->assertSame(45, DB::table('students')->where('tenant_id', $moein->id)->count());
        $this->assertSame(12, DB::table('students')->where('tenant_id', $salam->id)->count());
        $this->assertSame(9, $moein->classrooms()->count());
        $this->assertSame(0, $salam->classrooms()->count());
        $this->assertGreaterThanOrEqual(6, $moein->teachers()->count());
        $this->assertSame(3, $salam->consultants()->count());
        foreach ($moein->classrooms()->get() as $classroom) {
            $this->assertSame(5, $classroom->students()->count());
            $this->assertSame(6, $classroom->teachers()->count());
        }
        $assignments = DB::table('student_staff')->where('tenant_id', $salam->id)
            ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total');
        $this->assertSame(9, $assignments->filter(fn ($count) => (int) $count === 1)->count());
        $this->assertSame(3, $assignments->filter(fn ($count) => (int) $count === 2)->count());
        $this->assertSame(0, DB::table('classroom_student')->where('tenant_id', $salam->id)->count());
        $this->assertSame(114, DB::table('schedule_items')->count());
    }
}
