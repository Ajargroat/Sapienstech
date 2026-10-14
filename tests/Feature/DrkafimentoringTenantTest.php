<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DrkafimentoringTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DrkafimentoringTenantTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_EMAIL = 'kafi@drkafimentoring.ir';

    private const PASSWORD = 'password';

    /**
     * The drkafimentoring tenant with a primary and a secondary host.
     *
     * @return array{0: Tenant, 1: string, 2: string}
     */
    private function tenantWithDomains(): array
    {
        $tenant = Tenant::create([
            'name' => 'علیرضا کافی',
            'slug' => DrkafimentoringTenantSeeder::SLUG,
            'status' => 'active',
        ]);

        $hosts = [];
        foreach ([true, false] as $primary) {
            $host = DrkafimentoringTenantSeeder::SLUG.($primary ? '.test' : '.example.test');
            Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $host,
                'is_primary' => $primary,
            ]);
            Cache::forget(Domain::cacheKey($host));
            $hosts[] = $host;
        }

        return [$tenant, $hosts[0], $hosts[1]];
    }

    /**
     * @return array{0: Tenant, 1: string}
     */
    private function seedTenant(): array
    {
        [$tenant, $host] = $this->tenantWithDomains();
        (new DrkafimentoringTenantSeeder)->run();

        return [$tenant, $host];
    }

    private function owner(Tenant $tenant): User
    {
        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', self::OWNER_EMAIL)
            ->firstOrFail();
    }

    public function test_the_seeder_creates_the_owner_and_eight_students(): void
    {
        [$tenant] = $this->seedTenant();

        $owner = $this->owner($tenant);

        $this->assertSame(User::ROLE_TENANT_ADMIN, $owner->role);
        $this->assertSame(1, User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_TENANT_ADMIN)
            ->count());
        $this->assertSame($owner->id, (int) $tenant->fresh()->owner_user_id);
        $this->assertTrue(Hash::check(self::PASSWORD, $owner->password));

        $students = Student::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $this->assertCount(8, $students);

        $this->assertSame(8, DB::table('student_staff')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->count());

        // The structural "single non-hierarchical consultant" fact.
        $this->assertNull($tenant->fresh()->hierarchy_type);
    }

    public function test_the_owner_can_log_in_through_the_tenant_host_and_see_the_roster(): void
    {
        [$tenant, $host] = $this->seedTenant();
        $owner = $this->owner($tenant);

        $this->post("http://{$host}/login", [
            'email' => self::OWNER_EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('consultant.dashboard'));

        $this->assertAuthenticatedAs($owner);

        $this->get("http://{$host}/consultant/dashboard")
            ->assertOk()
            ->assertSee('آوا رستگار');
    }

    public function test_a_student_can_log_in_through_the_student_portal(): void
    {
        [$tenant, $host] = $this->seedTenant();

        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'student01@drkafimentoring.ir')
            ->firstOrFail();

        $this->post("http://{$host}/student/login", [
            'email' => 'student01@drkafimentoring.ir',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student, 'student');

        $this->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->assertSee('آوا رستگار');
    }

    /** The adversarial one: right email + right password, another tenant's host. */
    public function test_the_owner_cannot_log_in_through_another_tenants_domain(): void
    {
        $this->seedTenant();

        $other = Tenant::create(['name' => 'مؤسسهٔ دیگر', 'slug' => 'other-tenant', 'status' => 'active']);
        Domain::create(['tenant_id' => $other->id, 'domain' => 'other.example.test', 'is_primary' => true]);
        Cache::forget(Domain::cacheKey('other.example.test'));

        $this->post('http://other.example.test/login', [
            'email' => self::OWNER_EMAIL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seedTenant();

        $tables = [
            'users', 'students', 'student_staff', 'blog_posts', 'student_deals', 'deal_payments',
            'deal_notifications', 'exam_companies', 'company_exam_results', 'tests',
            'student_assigned_quizzes', 'student_test_attempts', 'schedule_items',
            'chat_conversations', 'chat_participants', 'chat_messages',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $this->artisan('tenant:seed', ['slug' => DrkafimentoringTenantSeeder::SLUG])
            ->assertSuccessful();

        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table.' must not grow on rerun');
        }

        $this->assertTrue(Hash::check(self::PASSWORD, $this->owner(Tenant::firstOrFail())->password));
    }

    public function test_the_seeded_tenant_renders_a_populated_student_portal(): void
    {
        [$tenant, $host] = $this->seedTenant();

        // student02 sits inside the deal decision window AND has the
        // comprehensive exam upcoming — the one row that exercises all three
        // dashboard surfaces at once.
        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'student02@drkafimentoring.ir')
            ->firstOrFail();

        $this->actingAs($student, 'student');

        $this->get("http://{$host}/student/dashboard")
            ->assertOk()
            ->assertSee('دورهٔ مشاورهٔ شما رو به پایان است')
            ->assertSee('مرور زیست‌شناسی')
            ->assertSee('آزمون جامع ریاضی — نوبت اول');
    }

    /**
     * PROPOSE verification row 28: a seeded completed attempt has no
     * `attempt_answers` rows, so the result page must tolerate an empty answer
     * set rather than error.
     */
    public function test_the_seeded_exam_result_page_renders(): void
    {
        [$tenant, $host] = $this->seedTenant();

        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', 'student01@drkafimentoring.ir')
            ->firstOrFail();

        $assignment = StudentAssignedQuiz::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->firstOrFail();

        $this->actingAs($student, 'student');

        $this->get("http://{$host}".route('student.exams.result', $assignment, false))
            ->assertOk();
    }
}
