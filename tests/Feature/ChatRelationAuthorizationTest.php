<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatActor;
use App\Support\ChatService;
use App\Support\SiteConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChatRelationAuthorizationTest extends TestCase
{
    private Tenant $tenant;
    private User $staff;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        // A dedicated connection makes this fixture safe even with a live .env.
        config(['database.default' => 'chat_relation_test', 'database.connections.chat_relation_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('chat_relation_test');
        Event::fake();
        $this->mock(SiteConfig::class)->shouldReceive('all')->andReturn([
            'chat' => ['enabled' => true, 'groups' => ['enabled' => true]],
        ]);

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
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('avatar')->nullable();
                $table->text('preferences')->nullable();
                if ($name === 'users') {
                    $table->string('role');
                } else {
                    $table->string('grade')->nullable();
                }
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_09_18_000001_create_tenant_hierarchy.php'))->up();
        (require database_path('migrations/2026_09_13_120000_create_chat_tables.php'))->up();

        $this->tenant = Tenant::create(['name' => 'Academy', 'slug' => 'chat-relation-test']);
        $this->tenant->forceFill(['hierarchy_type' => 'consultancy'])->save();
        app()->instance('tenant', $this->tenant);
        $this->staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'role' => User::ROLE_CONSULTANT_STAFF]);
        $this->student = Student::create(['tenant_id' => $this->tenant->id, 'name' => 'Student']);
        ChatService::$unreadMemo = [];
    }

    protected function tearDown(): void
    {
        DB::purge('chat_relation_test');
        parent::tearDown();
    }

    private function link(Student $student, ?User $staff = null): void
    {
        DB::table('student_staff')->insert([
            'tenant_id' => $this->tenant->id, 'student_id' => $student->id, 'user_id' => ($staff ?? $this->staff)->id,
        ]);
    }

    private function denied(callable $action, int $status = 404): void
    {
        try {
            $action();
            $this->fail('Expected relation authorization to deny access.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    public function test_explicit_staff_is_checked_independently_of_authenticated_staff(): void
    {
        $this->link($this->student);
        $this->actingAs($this->staff);
        $unrelated = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Other', 'role' => User::ROLE_CONSULTANT_STAFF]);

        $this->denied(fn () => ChatService::directThread($unrelated, $this->student));
        $this->denied(fn () => ChatService::createGroup($unrelated, 'No access', [$this->student->id]), 422);
        $this->assertSame(0, ChatConversation::count());
        $this->assertNotNull(ChatService::directThread($this->staff, $this->student)->id);
    }

    public function test_revocation_hides_direct_history_lists_and_unreads_for_both_actors(): void
    {
        $this->link($this->student);
        $conversation = ChatService::directThread($this->staff, $this->student);
        $staff = ChatActor::make($this->staff);
        $student = ChatActor::make($this->student);
        ChatService::sendMessage($conversation, $staff, 'Private', skipRateLimit: true);
        $this->assertSame(1, ChatService::unreadTotal($student));
        $conversation->load('participants');
        DB::table('student_staff')->delete();

        foreach ([$staff, $student] as $actor) {
            $this->assertCount(0, ChatService::conversationList($actor, 'Staff'));
            $this->assertSame(0, ChatService::unreadTotal($actor));
            $this->assertSame([], ChatService::unreadCounts($actor, [$conversation->id]));
            $this->denied(fn () => ChatService::messagePage($conversation, $actor));
            $this->denied(fn () => ChatService::sendMessage($conversation, $actor, 'Denied', skipRateLimit: true));
            $this->denied(fn () => ChatService::markRead($conversation, $actor));
        }
        $this->denied(fn () => ChatService::directThread($this->staff, $this->student));
    }

    public function test_group_rejects_unrelated_students_and_closes_access_after_any_revocation(): void
    {
        $peer = Student::create(['tenant_id' => $this->tenant->id, 'name' => 'Peer']);
        $this->link($this->student);
        $this->denied(fn () => ChatService::createGroup($this->staff, 'Group', [$this->student->id, $peer->id]), 422);
        $this->assertSame(0, ChatConversation::count());
        $this->link($peer);
        $conversation = ChatService::createGroup($this->staff, 'Group', [$this->student->id, $peer->id]);
        DB::table('student_staff')->where('student_id', $peer->id)->delete();

        foreach ([$this->staff, $this->student, $peer] as $model) {
            $actor = ChatActor::make($model);
            $this->assertCount(0, ChatService::conversationList($actor));
            $this->denied(fn () => ChatService::conversationPayload($conversation, $actor));
        }
    }

    public function test_foreign_staff_is_rejected_and_search_cannot_bypass_membership(): void
    {
        $foreignTenant = Tenant::create(['name' => 'Foreign', 'slug' => 'foreign-chat']);
        $foreign = User::create(['tenant_id' => $foreignTenant->id, 'name' => 'Foreign', 'role' => User::ROLE_CONSULTANT_STAFF]);
        $this->denied(fn () => ChatService::directThread($foreign, $this->student));
        $this->denied(fn () => ChatService::createGroup($foreign, 'Foreign', [$this->student->id]));

        $this->link($this->student);
        ChatService::directThread($this->staff, $this->student);
        $peer = Student::create(['tenant_id' => $this->tenant->id, 'name' => 'Peer']);
        $this->link($peer);
        $this->assertCount(0, ChatService::conversationList(ChatActor::make($peer), 'Staff'));
    }

    public function test_owner_and_legacy_access_remain_available_without_links(): void
    {
        $this->staff->forceFill(['role' => User::ROLE_TENANT_ADMIN])->save();
        $this->tenant->forceFill(['owner_user_id' => $this->staff->id])->save();
        $this->assertNotNull(ChatService::directThread($this->staff, $this->student)->id);
        $this->tenant->forceFill(['hierarchy_type' => null, 'owner_user_id' => null])->save();
        $legacy = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Legacy', 'role' => User::ROLE_CONSULTANT_STAFF]);
        $this->assertNotNull(ChatService::directThread($legacy, $this->student)->id);
    }

    public function test_shared_school_classroom_grants_access_until_removed(): void
    {
        $this->tenant->forceFill(['hierarchy_type' => 'school'])->save();
        $classroom = DB::table('classrooms')->insertGetId(['tenant_id' => $this->tenant->id, 'grade' => '10', 'name' => 'A']);
        DB::table('classroom_teacher')->insert(['tenant_id' => $this->tenant->id, 'classroom_id' => $classroom, 'user_id' => $this->staff->id, 'subject' => 'Math']);
        DB::table('classroom_student')->insert(['tenant_id' => $this->tenant->id, 'classroom_id' => $classroom, 'student_id' => $this->student->id]);
        $conversation = ChatService::directThread($this->staff, $this->student);
        $this->assertNotNull($conversation->id);
        DB::table('classroom_teacher')->delete();
        $this->denied(fn () => ChatService::assertMember($conversation, ChatActor::make($this->student)));
    }
}
