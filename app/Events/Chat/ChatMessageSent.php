<?php

namespace App\Events\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Support\ChatActor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new message landed. ShouldBroadcastNow (not the queued interface) so a
 * queue hiccup never delays realtime delivery; ChatService::dispatchSafely
 * swallows transport failures and the client's polling fallback covers them.
 *
 * Fan-out: the open thread (conversation channel) plus every other
 * participant's personal channel, so unread badges outside the thread update
 * too. The payload never reaches non-participants: both channel kinds
 * authorize membership server-side in routes/channels.php.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    public function broadcastAs(): string
    {
        return 'chat-message';
    }

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;
        $senderKey = ChatActor::keyFromColumns(
            $this->message->sender_user_id,
            $this->message->sender_student_id
        );

        $channels = [new PrivateChannel(ChatConversation::channelName((int) $conversation->id))];

        foreach (ChatParticipant::query()->where('conversation_id', $conversation->id)->get() as $participant) {
            if ($participant->actorKey() !== null && $participant->actorKey() !== $senderKey) {
                $channels[] = new PrivateChannel(ChatActor::channelName((string) $participant->actorKey()));
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->message->conversation_id,
            'message' => $this->message->toPayloadArray(),
        ];
    }
}
