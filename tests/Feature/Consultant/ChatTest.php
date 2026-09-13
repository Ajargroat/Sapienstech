<?php

namespace Tests\Feature\Consultant;

use App\Models\ChatMessage;
use App\Models\Domain;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Consultant-side direct chat: the page, thread/group creation, messaging,
 * membership enforcement and feature gating. Student-side behavior has its
 * own suite; cross-tenant data isolation of the models themselves is
 * additionally proven here at the HTTP layer (the BelongsToTenant scope must
 * 404 a foreign thread before any controller logic runs).
 */
class ChatTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithDomain(): array
    {
        $tenant = Tenant::factory()->create();
        $host = Str::lower(Str::random(10)).'.sapienstech.test';
        Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'is_primary' => true,
        ]);
        Cache::forget("domain:{$host}");

        return [$tenant, $host];
    }

    private function staffFor(Tenant $tenant): User
    {
        app()->instance('tenant', $tenant);

        return User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
    }

    private function studentFor(Tenant $tenant): Student
    {
        app()->instance('tenant', $tenant);

        return Student::factory()->create(['tenant_id' => $tenant->id]);
    }

    // ------------------------------------------------------------------
    // Page + feature gating
    // ------------------------------------------------------------------

    public function test_chat_page_loads_for_staff(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/direct-chat")
            ->assertOk()
            ->assertSee('data-chat-root', false)
            ->assertSee('گفتگوی مستقیم');
    }

    public function test_chat_page_404s_when_direct_chat_flag_is_off(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);

        site_override(['features' => ['direct_chat' => false]]);

        $this->actingAs($user)
            ->get("http://{$host}/consultant/direct-chat")
            ->assertNotFound();

        // JSON endpoints are gated by the same middleware.
        site_override(['features' => ['direct_chat' => false]]);
        $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations")
            ->assertNotFound();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();

        $this->get("http://{$host}/consultant/direct-chat")->assertRedirect();
    }

    // ------------------------------------------------------------------
    // Threads
    // ------------------------------------------------------------------

    public function test_staff_can_open_a_direct_thread_with_a_student_and_exchange_messages(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $created = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        );
        $created->assertCreated()->assertJsonPath('conversation.type', 'direct');

        $id = $created->json('conversation.id');

        // Opening the same pair again is idempotent (one thread per pair).
        $again = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        );
        $again->assertOk();
        $this->assertSame($id, $again->json('conversation.id'));

        $sent = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'سلام!']
        );
        $sent->assertCreated()->assertJsonPath('message.body', 'سلام!');

        $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$id}")
            ->assertOk()
            ->assertJsonPath('conversation.id', $id);

        $list = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations")
            ->assertOk();
        $this->assertCount(1, $list->json('conversations'));

        // The student is now on the other side of the same thread.
        $this->actingAs($student, 'student')
            ->postJson(
                "http://{$host}/student/direct-chat/conversations/{$id}/messages",
                ['body' => 'سلام استاد']
            )
            ->assertCreated();

        $page = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$id}/messages")
            ->assertOk();
        $this->assertCount(3, $page->json('messages')); // system + staff + student
    }

    public function test_messages_are_returned_in_chronological_order(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $thread = ChatService::directThread($user, $student);
        $id = $thread->id;

        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'اول']
        )->assertCreated();

        $this->actingAs($student, 'student')->postJson(
            "http://{$host}/student/direct-chat/conversations/{$id}/messages",
            ['body' => 'دوم']
        )->assertCreated();

        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'سوم']
        )->assertCreated();

        $ids = array_column($this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$id}/messages")
            ->assertOk()
            ->json('messages'), 'id');

        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'pages must be oldest-first so the renderer appends in order');
    }

    public function test_staff_can_send_an_image_attachment(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $thread = ChatService::directThread($user, $student);

        $response = $this->actingAs($user)
            ->post(
                "http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages",
                [
                    'body' => 'کلیپ توضیحی',
                    'attachment' => UploadedFile::fake()->createWithContent(
                        'poster.jpg',
                        "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01".str_repeat("\x00", 256)."\xFF\xD9"
                    ),
                ]
            )
            ->assertCreated();

        $message = ChatMessage::find($response->json('message.id'));
        $this->assertNotNull($message);
        $this->assertNotEmpty($message->attachment_path);
        $this->assertGreaterThan(0, (int) $message->attachment_size);
        $this->assertStringStartsWith('image/', (string) $message->attachment_mime);
        $this->assertEquals('poster.jpg', $message->attachment_name);
        $this->assertNotEmpty($response->json('message.attachment.url'));
        // The composer sends a caption together with its photo (Telegram
        // style), so a single message must carry and return both halves.
        $this->assertSame('کلیپ توضیحی', $message->body);
        $this->assertSame('کلیپ توضیحی', $response->json('message.body'));

        $file = public_path("tenants/{$tenant->slug}/{$message->attachment_path}");
        $this->assertFileExists($file);
        @unlink($file);
    }

    public function test_student_can_send_a_file_attachment(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $thread = ChatService::directThread($user, $student);

        $response = $this->actingAs($student, 'student')
            ->post(
                "http://{$host}/student/direct-chat/conversations/{$thread->id}/messages",
                ['attachment' => UploadedFile::fake()->createWithContent(
                    'question.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
                )]
            )
            ->assertCreated();

        $message = ChatMessage::find($response->json('message.id'));
        $this->assertNotNull($message);
        $this->assertGreaterThan(0, (int) $message->attachment_size);

        $file = public_path("tenants/{$tenant->slug}/{$message->attachment_path}");
        $this->assertFileExists($file);
        @unlink($file);
    }

    public function test_emoji_survive_the_round_trip(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $thread = ChatService::directThread($user, $student);
        $body = 'موفق باشید! 🌱👨‍👩‍👦❤️';

        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages",
            ['body' => $body]
        )->assertCreated()->assertJsonPath('message.body', $body);

        $this->assertSame($body, ChatMessage::latest('id')->first()->body);
    }

    public function test_staff_can_create_a_group_with_many_students_and_all_can_post(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $a = $this->studentFor($tenant);
        $b = $this->studentFor($tenant);

        $created = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/groups",
            ['title' => 'گروه کنکور', 'student_ids' => [$a->id, $b->id]]
        );
        $created->assertCreated()->assertJsonPath('conversation.type', 'group');
        $id = $created->json('conversation.id');

        $this->assertCount(3, $created->json('conversation.participants'));

        foreach ([$a, $b] as $student) {
            $this->actingAs($student, 'student')
                ->postJson(
                    "http://{$host}/student/direct-chat/conversations/{$id}/messages",
                    ['body' => 'پیام دانش‌آموز']
                )
                ->assertCreated();
        }

        // Both students see the group in their lists.
        foreach ([$a, $b] as $student) {
            $list = $this->actingAs($student, 'student')
                ->getJson("http://{$host}/student/direct-chat/conversations")
                ->assertOk();
            $this->assertSame([$id], collect($list->json('conversations'))->pluck('id')->all());
        }
    }

    public function test_group_without_students_is_rejected(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);

        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/groups",
            ['title' => 'گروه خالی', 'student_ids' => []]
        )->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Membership / authorization
    // ------------------------------------------------------------------

    public function test_a_staff_member_cannot_reach_another_staff_threads(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $owner = $this->staffFor($tenant);
        $other = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $id = $this->actingAs($owner)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        // Same tenant, not a participant → 404 (existence is not leaked).
        $this->actingAs($other)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$id}")
            ->assertNotFound();

        $this->actingAs($other)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'نوفوذ']
        )->assertNotFound();
    }

    public function test_a_student_cannot_reach_another_students_thread(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $mine = $this->studentFor($tenant);
        $theirs = $this->studentFor($tenant);

        $id = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $theirs->id]
        )->json('conversation.id');

        $this->actingAs($mine, 'student')
            ->getJson("http://{$host}/student/direct-chat/conversations/{$id}")
            ->assertNotFound();
    }

    public function test_a_thread_from_another_tenant_404s_on_this_tenants_domain(): void
    {
        [$tenantA, $hostA] = $this->tenantWithDomain();
        $userA = $this->staffFor($tenantA);
        $studentA = $this->studentFor($tenantA);

        [$tenantB, $hostB] = $this->tenantWithDomain();
        $userB = $this->staffFor($tenantB);
        $studentB = $this->studentFor($tenantB);

        $idB = $this->actingAs($userB)->postJson(
            "http://{$hostB}/consultant/direct-chat/conversations",
            ['student_id' => $studentB->id]
        )->json('conversation.id');

        // Valid consultant session on a valid tenant domain, but the thread
        // belongs to tenant B → the BelongsToTenant binding scope 404s it.
        $this->actingAs($userA)
            ->getJson("http://{$hostA}/consultant/direct-chat/conversations/{$idB}")
            ->assertNotFound();

        $this->actingAs($userA)->postJson(
            "http://{$hostA}/consultant/direct-chat/conversations/{$idB}/messages",
            ['body' => 'نوفوذ']
        )->assertNotFound();

        // And it never appears in tenant A's list.
        $list = $this->actingAs($userA)
            ->getJson("http://{$hostA}/consultant/direct-chat/conversations")
            ->assertOk();
        $this->assertSame([], array_values(array_filter(
            collect($list->json('conversations'))->pluck('id')->all(),
            fn ($id) => $id === $idB
        )));
    }

    public function test_only_the_owning_staff_can_close_or_rename_a_thread(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $owner = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $id = $this->actingAs($owner)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        $this->actingAs($student, 'student')->patchJson(
            "http://{$host}/student/direct-chat/conversations/{$id}",
            ['status' => 'closed']
        )->assertStatus(405); // student side has no update route at all

        $closed = $this->actingAs($owner)->patchJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}",
            ['status' => 'closed']
        );
        $closed->assertOk()->assertJsonPath('conversation.status', 'closed');

        // Closed → students cannot write into the thread anymore.
        $this->actingAs($student, 'student')->postJson(
            "http://{$host}/student/direct-chat/conversations/{$id}/messages",
            ['body' => 'قفل است؟']
        )->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Validation / limits
    // ------------------------------------------------------------------

    public function test_empty_messages_are_rejected_and_length_is_tenant_configurable(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);
        $id = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => '   ']
        )->assertStatus(422);

        site_override(['chat' => ['message_max_length' => 100]]);
        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => str_repeat('الف', 60)]
        )->assertStatus(422);
    }

    public function test_send_rate_limit_comes_from_chat_config(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);
        $id = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        site_override(['chat' => ['rate_limit_per_minute' => 2]]);
        Cache::clear(); // RateLimiter uses the cache store; start clean.

        $url = "http://{$host}/consultant/direct-chat/conversations/{$id}/messages";
        $this->actingAs($user)->postJson($url, ['body' => 'یک'])->assertCreated();
        $this->actingAs($user)->postJson($url, ['body' => 'دو'])->assertCreated();
        $this->actingAs($user)->postJson($url, ['body' => 'سه'])->assertStatus(429);
    }

    // ------------------------------------------------------------------
    // Read receipts / unread counters
    // ------------------------------------------------------------------

    public function test_unread_counts_and_read_marks_round_trip(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);

        $thread = ChatService::directThread($user, $student);

        $this->actingAs($student, 'student')->postJson(
            "http://{$host}/student/direct-chat/conversations/{$thread->id}/messages",
            ['body' => 'سلام']
        )->assertCreated();

        $list = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations")
            ->assertOk();
        $this->assertSame(1, $list->json('conversations.0.unread'));

        $this->actingAs($user)
            ->postJson("http://{$host}/consultant/direct-chat/conversations/{$thread->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        $list = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations")
            ->assertOk();
        $this->assertSame(0, $list->json('conversations.0.unread'));

        // The consultant's read marker must surface as a "seen" receipt on
        // the student's side of the thread (drives the double tick), and
        // vice versa once the student reads the consultant's reply.
        $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages",
            ['body' => 'سلام، در خدمتم']
        )->assertCreated()->assertJsonPath('message.mine', true);

        // A just-sent message is "sent" (single check), never "seen": the
        // fresh send response must carry no read receipt yet.
        $sent = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages",
            ['body' => 'خبر']
        )->assertCreated()->json('message');
        $this->assertTrue($sent['mine']);
        $this->assertFalse((bool) ($sent['seen'] ?? false));

        $studentView = $this->actingAs($student, 'student')
            ->getJson("http://{$host}/student/direct-chat/conversations/{$thread->id}/messages")
            ->assertOk();
        $mine = collect($studentView->json('messages'))
            ->firstWhere('mine', true);
        $this->assertNotNull($mine);
        $this->assertTrue($mine['seen']);
        $this->assertSame(1, $mine['seen_count']);

        $this->actingAs($student, 'student')
            ->postJson("http://{$host}/student/direct-chat/conversations/{$thread->id}/read")
            ->assertOk();

        $staffView = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$thread->id}/messages")
            ->assertOk();
        $mine = collect($staffView->json('messages'))
            ->firstWhere('mine', true);
        $this->assertNotNull($mine);
        $this->assertTrue($mine['seen']);
        $this->assertSame(1, $mine['seen_count']);

        // Receipts are one-sided: the viewer's OWN messages carry the seen
        // fields (drive their ticks); peer messages must not carry them at
        // all, so no renderer can ever draw a checkmark on their bubbles.
        $theirs = collect($staffView->json('messages'))->firstWhere('mine', false);
        $this->assertNotNull($theirs);
        $this->assertArrayNotHasKey('seen', $theirs);
        $this->assertArrayNotHasKey('seen_count', $theirs);
    }

    public function test_edited_messages_are_marked_and_deleted_messages_vanish(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);
        $id = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        $msg = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'قابل ویرایش']
        )->json('message');

        $mid = $msg['id'];

        $this->actingAs($user)->putJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}",
            ['body' => 'ویرایش شد']
        )->assertOk()->assertJsonPath('message.edited', true);

        $this->actingAs($user)->deleteJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}"
        )->assertOk();

        // Deletion is permanent: no tombstone row, no tombstone payload —
        // the message simply stops existing for everyone.
        $page = $this->actingAs($user)
            ->getJson("http://{$host}/consultant/direct-chat/conversations/{$id}/messages")
            ->assertOk();

        $this->assertNotContains($mid, collect($page->json('messages'))->pluck('id')->all());
        $this->assertDatabaseMissing('chat_messages', ['id' => $mid]);

        // A second delete (or a stale client) must 404, not tombstone.
        $this->actingAs($user)->deleteJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}"
        )->assertNotFound();
    }

    public function test_messages_are_editable_for_a_week_and_locked_after(): void
    {
        [$tenant, $host] = $this->tenantWithDomain();
        $user = $this->staffFor($tenant);
        $student = $this->studentFor($tenant);
        $id = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations",
            ['student_id' => $student->id]
        )->json('conversation.id');

        $mid = $this->actingAs($user)->postJson(
            "http://{$host}/consultant/direct-chat/conversations/{$id}/messages",
            ['body' => 'قدیمی']
        )->json('message.id');

        // Six days in: still inside the one-week window.
        $this->travelTo(now()->addDays(6));
        $this->actingAs($user)->putJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}",
            ['body' => 'ویرایشِ روز ششم']
        )->assertOk()->assertJsonPath('message.body', 'ویرایشِ روز ششم');

        // Eight days in: the week is over, the message is frozen.
        $this->travelTo(now()->addDays(2));
        $this->actingAs($user)->putJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}",
            ['body' => 'تغییر ممنوع']
        )->assertForbidden();

        $this->travelBack();
        $this->assertDatabaseHas('chat_messages', ['id' => $mid, 'body' => 'ویرایشِ روز ششم']);

        // Deleting, unlike editing, stays allowed after the week.
        $this->actingAs($user)->deleteJson(
            "http://{$host}/consultant/direct-chat/messages/{$mid}"
        )->assertOk();
        $this->assertDatabaseMissing('chat_messages', ['id' => $mid]);
    }
}
