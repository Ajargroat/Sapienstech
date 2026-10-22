<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\StudentAssignedQuiz;
use App\Models\StudentJoinRequest;
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

    /**
     * The visitor's whole journey on this tenant's own site: the landing page
     * carries the membership form (the `join` section is in the tenant file's
     * `sections`), submitting it files a pending request rather than creating a
     * student, and the consultant sees that request in their queue.
     *
     * Nothing else in the suite renders *this* tenant's landing page, so this
     * is what would catch the section silently disappearing again.
     */
    public function test_a_visitor_can_file_a_join_request_from_this_tenants_landing_page(): void
    {
        [$tenant, $host] = $this->seedTenant();

        $this->get("http://{$host}/")
            ->assertOk()
            ->assertSee('درخواست عضویت')
            ->assertSee(route('join.store'), false);

        $this->post("http://{$host}/join", [
            'name' => 'زهرا محمدی',
            'email' => 'zahra@drkafimentoring.ir',
            'phone' => '09120000000',
            'grade' => 'دهم',
            'reference' => 'REF-12345',
        ])->assertRedirect();
        // Pending, and not on the roster: an unapproved visitor must never
        // appear among the eight seeded students.
        $this->assertSame(1, StudentJoinRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', StudentJoinRequest::STATUS_PENDING)
            ->count());

        $this->assertSame(8, Student::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count());

        // And the consultant can act on it.
        $this->actingAs($this->owner($tenant))
            ->get("http://{$host}/consultant/requests")
            ->assertOk()
            ->assertSee('زهرا محمدی');
    }

    /**
     * A visitor with no account must be able to *find* the flow.
     *
     * The form is useless if nothing links to it: before this, the nav's only
     * destination was the login page and the hero's primary button pointed at
     * '#'. This pins the three entry points, and the login page's way out for
     * someone who has no account yet.
     */
    public function test_the_join_flow_is_reachable_from_the_pages_entry_points(): void
    {
        [, $host] = $this->seedTenant();

        $landing = $this->get("http://{$host}/")->assertOk()->getContent();

        // The nav link, the hero button and the closing band all lead to the
        // plans grid, which is where the join flow starts.
        $this->assertStringContainsString('>طرح‌ها</a>', $landing);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($landing, 'href="#plans"'),
            'the hero and the closing call-to-action should both point at the plans grid',
        );

        // The login page offers a visitor without an account a way forward.
        $this->get("http://{$host}/login")
            ->assertOk()
            ->assertSee('حساب کاربری ندارید؟ درخواست عضویت دهید')
            ->assertSee(route('home').'#plans', false);
    }

    /**
     * The shipped login shell must stay the shell.
     *
     * A shell that silently fails to dispatch is indistinguishable from one
     * that was never written: AuthSurface falls back to the shared card, the
     * route still answers 200, and the signup-link assertion above still
     * passes. Pin the bespoke frame and the unchanged security contract.
     */
    public function test_the_login_page_carries_the_bespoke_shell(): void
    {
        [, $host] = $this->seedTenant();

        $content = $this->get("http://{$host}/login")->assertOk()->getContent();

        $this->assertStringContainsString('data-auth-surface="drkafimentoring"', $content);
        $this->assertStringContainsString('<body class="ka-auth">', $content);
        $this->assertStringContainsString('ka-auth__panel', $content);

        // The card is embedded once and unchanged: both guards' forms, one
        // CSRF token each, and one document.
        $this->assertSame(1, substr_count($content, 'class="login-card'));
        $this->assertSame(2, substr_count($content, 'name="_token"'));
        $this->assertSame(2, substr_count($content, 'name="identifier"'));
        $this->assertSame(1, substr_count($content, '<!DOCTYPE html>'));
    }

    public function test_the_owner_can_log_in_through_the_tenant_host_and_see_the_roster(): void
    {
        [$tenant, $host] = $this->seedTenant();
        $owner = $this->owner($tenant);

        $this->post("http://{$host}/login", [
            'identifier' => self::OWNER_EMAIL,
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
            'identifier' => 'student01@drkafimentoring.ir',
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
            'identifier' => self::OWNER_EMAIL,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('identifier');

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
