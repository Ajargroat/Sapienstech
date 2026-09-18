<?php

namespace Tests\Unit;

use App\Events\Chat\ChatMessageChanged;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatRead;
use App\Events\Chat\ChatTyping;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ChatActor;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Channel topology for the realtime transport: which channels each event
 * touches, and that every channel name follows the patterns authorized in
 * routes/channels.php (chat.conversation.{id} / chat.actor.{u|s-id}).
 *
 * The events are asserted as ShouldBroadcastNow (synchronous delivery —
 * queue latency must not sit in front of "realtime").
 */
class ChatBroadcastChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function thread(): array
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $staff = User::factory()->consultant()->create(['tenant_id' => $tenant->id]);
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = ChatConversation::create([
            'tenant_id' => $tenant->id,
            'staff_user_id' => $staff->id,
            'student_id' => $student->id,
        ]);
        $conversation->participants()->create([
            'tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'owner',
        ]);
        $conversation->participants()->create([
            'tenant_id' => $tenant->id, 'student_id' => $student->id,
        ]);

        $message = ChatMessage::create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'sender_user_id' => $staff->id,
        ]);

        return [$conversation, $message, $staff, $student];
    }

    public function test_all_chat_events_are_synchronously_broadcastable(): void
    {
        foreach ([ChatMessageSent::class, ChatMessageChanged::class, ChatRead::class, ChatTyping::class] as $class) {
            $this->assertTrue(
                is_subclass_of($class, ShouldBroadcastNow::class),
                "{$class} must implement ShouldBroadcastNow"
            );
        }
    }

    public function test_message_sent_fans_out_to_the_conversation_and_the_other_participant_only(): void
    {
        [$conversation, $message, $staff, $student] = $this->thread();

        $event = new ChatMessageSent($message);
        $names = array_map(
            fn ($c) => str_replace('private-', '', (string) $c),
            $event->broadcastOn()
        );

        sort($names);
        $this->assertSame([
            'chat.actor.s-'.$student->id,
            'chat.conversation.'.$conversation->id,
        ], $names, 'Sender gets no personal ping for their own message.');
    }

    public function test_read_and_typing_events_target_the_conversation_channel(): void
    {
        [$conversation, $message, $staff] = $this->thread();

        $read = new ChatRead($conversation, ChatActor::make($staff), (int) $message->id);
        $names = array_map(
            fn ($c) => str_replace('private-', '', (string) $c),
            $read->broadcastOn()
        );

        $this->assertContains('chat.conversation.'.$conversation->id, $names);
        $this->assertContains('chat.actor.u-'.$staff->id, $names);

        $typing = new ChatTyping($conversation, ChatActor::make($staff));
        $this->assertSame(
            ['chat.conversation.'.$conversation->id],
            array_map(fn ($c) => str_replace('private-', '', (string) $c), $typing->broadcastOn())
        );
    }

    public function test_payloads_are_minimal_and_id_keyed(): void
    {
        [$conversation, $message, $staff] = $this->thread();

        $payload = (new ChatMessageSent($message))->broadcastWith();
        $this->assertSame((int) $conversation->id, $payload['conversation_id']);
        $this->assertSame((int) $message->id, $payload['message']['id']);

        $changed = (new ChatMessageChanged($message, 'deleted'))->broadcastWith();
        $this->assertSame('deleted', $changed['action']);
    }

    public function test_channel_names_are_private_channel_objects(): void
    {
        [$conversation, $message] = $this->thread();

        foreach ((new ChatMessageSent($message))->broadcastOn() as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel);
        }
    }
}
