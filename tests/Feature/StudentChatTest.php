<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatActor;
use App\Support\ChatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Student-side chat: the student_chat flag, "consultant opens it, student
 * answers only", and the structural no-DM rule (there is no student-facing
 * create endpoint, and the schema cannot represent student↔student).
 */
class StudentChatTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create(['tenant_id' => $tenant->id, 'domain' => $host, 'is_primary' => true]);
        Cache::forget("domain:{$host}");

        return [$tenant, $host];
    }

    private function staff(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    private function student(Tenant $tenant): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_student_chat_page_loads_when_enabled(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->student($tenant);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/direct-chat")
            ->assertOk()
            ->assertSee('data-chat-root', false);
    }

    public function test_student_chat_page_404s_when_the_student_flag_is_off(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->student($tenant);

        site_override(['features' => ['student_chat' => false]]);

        $this->actingAs($student, 'student')
            ->get("http://{$host}/student/direct-chat")
            ->assertNotFound();
    }

    public function test_students_have_no_conversation_creation_endpoint(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $student = $this->student($tenant);

        // The whole chat prefix exists for students, but POST …/conversations
        // is only ever defined for consultants — 405, not a hole.
        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/direct-chat/conversations", ['student_id' => 1])
            ->assertStatus(405);

        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/direct-chat/groups", ['title' => 'x', 'student_ids' => [1]])
            ->assertStatus(404); // no /groups path under student at all
    }

    public function test_students_never_see_or_join_peer_threads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->staff($tenant);
        $a = $this->student($tenant);
        $b = $this->student($tenant);

        // A gets a direct thread; B has none.
        $thread = ChatService::directThread($staff, $a);

        $listB = $this->actingAs($b, 'student')
            ->getJson("http://{$host}/student/direct-chat/conversations")
            ->assertOk();
        $this->assertSame([], $listB->json('conversations'));

        $this->actingAs($b, 'student')
            ->getJson("http://{$host}/student/direct-chat/conversations/{$thread->id}")
            ->assertNotFound();

        // B cannot even type into A's thread.
        $this->actingAs($b, 'student')
            ->postJson("http://{$host}/student/direct-chat/conversations/{$thread->id}/typing")
            ->assertNotFound();
    }

    public function test_student_unread_badge_reflects_staff_replies(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->staff($tenant);
        $student = $this->student($tenant);

        $thread = ChatService::directThread($staff, $student);

        $this->actingAs($staff)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages",
            ['body' => 'سلام']
        )->assertCreated();

        $this->actingAs($student, 'student')
            ->getJson("http://{$host}/student/direct-chat/unread")
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/direct-chat/conversations/{$thread->id}/read")
            ->assertOk();

        $this->actingAs($student, 'student')
            ->getJson("http://{$host}/student/direct-chat/unread")
            ->assertOk()
            ->assertJsonPath('unread_total', 0);
    }

    public function test_group_membership_grants_access_and_marks_join_visible(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $staff = $this->staff($tenant);
        $student = $this->student($tenant);

        $group = ChatService::createGroup($staff, 'گروه آزمایشی', [$student->id]);

        $page = $this->actingAs($student, 'student')
            ->getJson("http://{$host}/student/direct-chat/conversations/{$group->id}")
            ->assertOk();

        $this->assertSame('group', $page->json('conversation.type'));
        $this->assertCount(2, $page->json('conversation.participants'));
        // The opening system message is present for the student too.
        $this->assertSame('system', $page->json('page.messages.0.type'));
    }

    public function test_chat_actor_keys_stay_disambiguated_across_id_spaces(): void
    {
        [$tenant] = $this->tenantWithDomain();
        $staff = $this->staff($tenant);
        $student = $this->student($tenant);

        $this->assertNotSame(
            ChatActor::make($staff)->key(),
            ChatActor::make($student)->key(),
            'Even with colliding numeric ids, u:/s: prefixes must separate the actors.'
        );
    }
}
