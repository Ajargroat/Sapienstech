<?php

namespace Tests\Feature;

use App\Models\DealNotification;
use App\Models\DealPayment;
use App\Models\Domain;
use App\Models\Student;
use App\Models\StudentDeal;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DealService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Mockery;
use Tests\TestCase;

/**
 * The renewal feature end to end: dated deals per student, the decision
 * window (continue/withdraw), transfer-receipt submission, owner verification
 * and the exactly-once renewal chain — plus the tenant walls around all of it.
 *
 * Isolated sqlite (never the developer's database) with the real migrations,
 * mirroring HierarchyAccessTest.
 */
class StudentDealTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never migrate or transact on the connection from the developer's .env.
        config(['database.default' => 'deals_test', 'database.connections.deals_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array', 'session.driver' => 'array', 'tenancy.fallback_domains' => [], 'hashing.bcrypt.rounds' => 4]);
        DB::purge('deals_test');
        Event::fake();

        $site = $this->mock(SiteConfig::class);
        $site->shouldReceive('personalize')->andReturnSelf();
        $site->shouldReceive('all')->andReturn(['features' => [
            'dashboard' => true, 'deals' => true, 'student_profile' => true,
        ]]);

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamps();
        });
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
        (require database_path('migrations/2026_09_18_000002_create_student_deals.php'))->up();

        // JSON error responses avoid rendering error layouts against the minimal schema.
        $this->withHeader('Accept', 'application/json');
        View::partialMock()->shouldReceive('make')->andReturnUsing(function ($name, $data = []) {
            $view = Mockery::mock(\Illuminate\View\View::class);
            $view->shouldReceive('render')->andReturn('Deal fixture view');
            $view->shouldReceive('getData', 'gatherData')->andReturn($data);
            $view->shouldReceive('name')->andReturn($name);

            return $view;
        });

        Cache::flush();
    }

    protected function tearDown(): void
    {
        DB::purge('deals_test');
        parent::tearDown();
    }

    // ------------------------------------------------------------------ //
    //  Helpers
    // ------------------------------------------------------------------ //

    protected function academy(string $slug = 'academy'): Tenant
    {
        $tenant = Tenant::create(['name' => $slug, 'slug' => $slug, 'hierarchy_type' => 'consultancy']);
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $slug.'.deals.test']);

        return $tenant;
    }

    protected function owner(Tenant $tenant): User
    {
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'role' => User::ROLE_TENANT_ADMIN,
            'email' => 'owner@'.$tenant->slug.'.test', 'password' => 'DealsTest!2026']);
        $tenant->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner;
    }

    protected function consultant(Tenant $tenant): User
    {
        return User::create(['tenant_id' => $tenant->id, 'name' => 'Consultant', 'role' => User::ROLE_CONSULTANT_STAFF,
            'email' => 'consultant@'.$tenant->slug.'.test', 'password' => 'DealsTest!2026']);
    }

    protected function pupil(Tenant $tenant, string $name = 'Student'): Student
    {
        return Student::create(['tenant_id' => $tenant->id, 'name' => $name, 'grade' => 'دهم',
            'email' => strtolower(str_replace(' ', '', $name)).'-'.$tenant->slug.'@deals.test', 'password' => 'DealsTest!2026']);
    }

    protected function assign(User $staff, Student $student): void
    {
        $staff->students()->attach($student->id, ['tenant_id' => $staff->tenant_id]);
    }

    protected function deal(Tenant $tenant, Student $student, string $endsOn = '+3 days', array $extra = []): StudentDeal
    {
        return StudentDeal::create($extra + [
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'starts_on' => now()->subDays(27)->toDateString(),
            'ends_on' => now()->modify($endsOn)->toDateString(),
            'amount' => 5_000_000,
            'period_days' => 30,
        ]);
    }

    protected function url(Tenant $tenant, string $path): string
    {
        return 'http://'.$tenant->slug.'.deals.test'.$path;
    }

    // ------------------------------------------------------------------ //
    //  Schema integrity
    // ------------------------------------------------------------------ //

    public function test_migration_rejects_cross_tenant_deal_links(): void
    {
        $local = $this->academy('local');
        $localStudent = $this->pupil($local);
        $foreignStudent = $this->pupil($this->academy('foreign'));
        $deal = $this->deal($local, $localStudent);

        try {
            DB::table('student_deals')->insert([
                'tenant_id' => $local->id, 'student_id' => $foreignStudent->id,
                'starts_on' => '2026-01-01', 'ends_on' => '2026-01-31',
                'amount' => 1, 'period_days' => 1,
            ]);
            $this->fail('A cross-tenant deal was accepted.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        try {
            DB::table('deal_payments')->insert([
                'tenant_id' => $local->id, 'deal_id' => $deal->id,
                'student_id' => $foreignStudent->id, 'reference' => 'R-1', 'amount' => 1,
            ]);
            $this->fail('A cross-tenant payment was accepted.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }
    }

    public function test_payment_references_are_unique_within_a_tenant(): void
    {
        $tenant = $this->academy('uniq');
        $deal = $this->deal($tenant, $this->pupil($tenant));
        DB::table('deal_payments')->insert([
            'tenant_id' => $tenant->id, 'deal_id' => $deal->id,
            'student_id' => $deal->student_id, 'reference' => 'TRX-100', 'amount' => 1,
        ]);

        $this->expectException(QueryException::class);
        DB::table('deal_payments')->insert([
            'tenant_id' => $tenant->id, 'deal_id' => $deal->id,
            'student_id' => $deal->student_id, 'reference' => 'TRX-100', 'amount' => 2,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Manager (consultant side)
    // ------------------------------------------------------------------ //

    public function test_owner_creates_deal_and_student_sees_it(): void
    {
        $tenant = $this->academy('create');
        $owner = $this->owner($tenant);
        $student = $this->pupil($tenant);

        $this->actingAs($owner)
            ->post($this->url($tenant, '/consultant/deals'), [
                'student_id' => $student->id,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(4)->toDateString(),
                'amount' => 5_000_000,
            ])->assertRedirect();

        $deal = StudentDeal::query()->sole();
        $this->assertSame($student->id, $deal->student_id);
        $this->assertSame(5, $deal->period_days, 'period_days is computed from the dates');
        $this->assertSame(StudentDeal::DECISION_PENDING, $deal->decision);
        $this->assertNull($deal->renewed_at);

        $this->actingAs($student, 'student')
            ->get($this->url($tenant, '/student/deals'))
            ->assertOk();
    }

    public function test_consultant_can_read_but_not_write_deals(): void
    {
        $tenant = $this->academy('roles');
        $owner = $this->owner($tenant);
        $consultant = $this->consultant($tenant);
        $student = $this->pupil($tenant);
        $this->assign($consultant, $student);

        $this->actingAs($consultant)
            ->get($this->url($tenant, '/consultant/deals'))
            ->assertOk();

        $this->actingAs($consultant)
            ->post($this->url($tenant, '/consultant/deals'), [
                'student_id' => $student->id,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDay()->toDateString(),
                'amount' => 1000,
            ])->assertForbidden();

        $this->actingAs($owner)
            ->post($this->url($tenant, '/consultant/deals'), [
                'student_id' => $student->id, 'consultant_id' => $consultant->id,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(2)->toDateString(),
                'amount' => 1000,
            ])->assertRedirect();

        $this->assertSame(1, StudentDeal::count());
        $this->assertSame(0, DealPayment::count());
    }

    public function test_deal_actions_are_reachable_only_from_the_owning_tenant(): void
    {
        $tenantA = $this->academy('tenant-a');
        $ownerA = $this->owner($tenantA);
        $studentA = $this->pupil($tenantA);
        $dealA = $this->deal($tenantA, $studentA);

        $tenantB = $this->academy('tenant-b');
        $ownerB = $this->owner($tenantB);
        $studentB = $this->pupil($tenantB);
        $dealB = $this->deal($tenantB, $studentB);

        // Owner A cannot remind tenant B's deal through their own domain.
        $this->actingAs($ownerA)
            ->postJson($this->url($tenantA, "/consultant/deals/{$dealB->id}/remind"))
            ->assertNotFound();

        // Student B cannot decide on tenant A's deal through their own domain.
        $this->actingAs($studentB, 'student')
            ->postJson($this->url($tenantB, "/student/deals/{$dealA->id}/decision"), ['decision' => 'continue'])
            ->assertNotFound();

        // ...and nothing crossed tenants on the way.
        $this->assertTrue(
            StudentDeal::query()
                ->where('tenant_id', $tenantA->id)
                ->whereKey($dealB->id)
                ->doesntExist()
        );
    }

    // ------------------------------------------------------------------ //
    //  Decision window
    // ------------------------------------------------------------------ //

    public function test_decisions_and_payments_are_blocked_before_the_window(): void
    {
        $tenant = $this->academy('window');
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student, '+20 days');

        $this->actingAs($student, 'student')
            ->postJson($this->url($tenant, "/student/deals/{$deal->id}/decision"), ['decision' => 'continue'])
            ->assertUnprocessable();

        $this->actingAs($student, 'student')
            ->postJson($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-1'])
            ->assertUnprocessable();

        $this->assertSame(0, DealPayment::count());
        $this->assertSame(StudentDeal::DECISION_PENDING, $deal->fresh()->decision);
    }

    public function test_student_can_continue_submit_and_then_change_mind(): void
    {
        $tenant = $this->academy('flip');
        $owner = $this->owner($tenant);
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student);

        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/decision"), ['decision' => 'continue'])
            ->assertRedirect();
        $this->assertSame(StudentDeal::DECISION_CONTINUE, $deal->fresh()->decision);

        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-A'])
            ->assertRedirect();
        $payment = DealPayment::query()->sole();
        $this->assertSame(DealPayment::STATUS_PENDING, $payment->status);
        $this->assertSame(5_000_000, $payment->amount, 'amount must be copied from the deal');

        // Changing the mind to withdraw cancels the pending receipt…
        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/decision"), [
                'decision' => 'withdraw', 'decision_note' => 'شروع معوق',
            ])->assertRedirect();

        $this->assertSame(DealPayment::STATUS_CANCELLED, $payment->fresh()->status);
        $this->assertSame(StudentDeal::DECISION_WITHDRAW, $deal->fresh()->decision);
        $this->assertNotNull($deal->fresh()->decided_at);

        // …and a cancelled payment can never be approved.
        $this->actingAs($owner)
            ->postJson($this->url($tenant, '/consultant/deals/payments/'.$payment->id.'/review'), ['status' => 'paid'])
            ->assertUnprocessable();
        $this->assertNull($deal->fresh()->renewed_at);
    }

    public function test_client_input_cannot_set_payment_state_and_references_are_unique(): void
    {
        $tenant = $this->academy('guard');
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student);

        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/decision"), ['decision' => 'continue'])
            ->assertRedirect();

        // A client cannot push a paid status or its own amount: only the
        // owner endpoint reviews, and the amount is copied from the deal.
        $this->actingAs($student, 'student')
            ->postJson($this->url($tenant, "/student/deals/{$deal->id}/payments"), [
                'reference' => 'TRX-9', 'status' => 'paid', 'amount' => 1,
            ])->assertRedirect();

        $payment = DealPayment::query()->sole();
        $this->assertSame(DealPayment::STATUS_PENDING, $payment->status);
        $this->assertSame(5_000_000, $payment->amount);

        // The same reference cannot be submitted twice.
        $this->actingAs($student, 'student')
            ->postJson($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-9'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reference');
    }

    // ------------------------------------------------------------------ //
    //  Owner verification and the renewal chain
    // ------------------------------------------------------------------ //

    public function test_owner_approval_renews_exactly_once(): void
    {
        $tenant = $this->academy('renew');
        $owner = $this->owner($tenant);
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student, '+2 days');

        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/decision"), ['decision' => 'continue'])
            ->assertRedirect();
        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-OK'])
            ->assertRedirect();
        $payment = DealPayment::query()->sole();

        $this->actingAs($owner)
            ->post($this->url($tenant, '/consultant/deals/payments/'.$payment->id.'/review'), ['status' => 'paid'])
            ->assertRedirect();

        $this->assertSame(DealPayment::STATUS_PAID, $payment->fresh()->status);
        $this->assertNotNull($deal->fresh()->renewed_at);

        $next = StudentDeal::query()->where('previous_deal_id', $deal->id)->sole();
        $this->assertSame(now()->addDays(3)->toDateString(), $next->starts_on->toDateString());
        $this->assertSame(now()->addDays(32)->toDateString(), $next->ends_on->toDateString());
        $this->assertSame(5_000_000, $next->amount);
        $this->assertSame(30, $next->period_days);
        $this->assertSame(StudentDeal::DECISION_PENDING, $next->decision);

        // Replaying the approval, or paying the renewed deal again, changes nothing.
        $this->actingAs($owner)
            ->postJson($this->url($tenant, '/consultant/deals/payments/'.$payment->id.'/review'), ['status' => 'paid'])
            ->assertUnprocessable();
        $this->actingAs($student, 'student')
            ->postJson($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-LATE'])
            ->assertUnprocessable();

        $this->assertCount(1, StudentDeal::query()->where('previous_deal_id', $deal->id)->get());
        $this->assertSame(1, DealPayment::count());
    }

    public function test_owner_rejection_keeps_room_for_a_new_receipt(): void
    {
        $tenant = $this->academy('reject');
        $owner = $this->owner($tenant);
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student);

        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/decision"), ['decision' => 'continue'])
            ->assertRedirect();
        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-BAD'])
            ->assertRedirect();
        $payment = DealPayment::query()->sole();

        $this->actingAs($owner)
            ->post($this->url($tenant, '/consultant/deals/payments/'.$payment->id.'/review'), [
                'status' => 'rejected', 'review_note' => 'واریزی یافت نشد',
            ])->assertRedirect();

        $this->assertSame(DealPayment::STATUS_REJECTED, $payment->fresh()->status);
        $this->assertSame('واریزی یافت نشد', $payment->fresh()->review_note);
        $this->assertNull($deal->fresh()->renewed_at);

        // After a rejection the student can submit a new reference.
        $this->actingAs($student, 'student')
            ->post($this->url($tenant, "/student/deals/{$deal->id}/payments"), ['reference' => 'TRX-C'])
            ->assertRedirect();
        $this->assertSame(2, DealPayment::count());
        $this->assertSame(DealPayment::STATUS_PENDING, DealPayment::query()->latest('id')->first()->status);
    }

    // ------------------------------------------------------------------ //
    //  Reminders
    // ------------------------------------------------------------------ //

    public function test_reminders_are_deduped_per_day_and_skip_withdrawn_deals(): void
    {
        $tenant = $this->academy('remind');
        $student = $this->pupil($tenant);
        $deal = $this->deal($tenant, $student);

        $this->assertNotNull(DealService::remind($deal));
        $this->assertNull(DealService::remind($deal)); // same-day dedupe
        $this->assertSame(0, DealService::remindDue()); // scheduler finds nothing new
        $this->assertSame(1, DealNotification::count());

        DealService::decide($deal, StudentDeal::DECISION_WITHDRAW);
        $this->assertNull(DealService::remind($deal));
        // The decision itself is a separate notification kind; no new reminder.
        $this->assertSame(1, DealNotification::query()->where('kind', 'reminder')->count());
    }

    public function test_scheduler_covers_all_active_tenants_but_skips_suspended(): void
    {
        $active = $this->academy('active');
        $suspended = $this->academy('suspended');
        $suspended->forceFill(['status' => 'suspended'])->save();

        $this->deal($active, $this->pupil($active));
        $this->deal($suspended, $this->pupil($suspended));

        $this->assertSame(1, DealService::remindDue());
        $this->assertSame(0, DealService::remindDue()); // rerun is a no-op
        $this->assertSame(1, DealNotification::count());
    }

    public function test_student_page_persists_a_window_prompt_and_marks_it_read(): void
    {
        $tenant = $this->academy('prompt');
        $student = $this->pupil($tenant);
        $this->deal($tenant, $student);

        $this->actingAs($student, 'student')
            ->get($this->url($tenant, '/student/deals'))
            ->assertOk();

        $this->assertSame(1, DealNotification::count());

        $notification = DealNotification::query()->sole();
        $this->actingAs($student, 'student')
            ->post($this->url($tenant, '/student/notifications/'.$notification->id.'/read'))
            ->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
