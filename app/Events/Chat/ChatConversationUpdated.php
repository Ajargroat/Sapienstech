<?php

namespace App\Events\Chat;

use App\Models\ChatConversation;
use App\Models\ChatParticipant;
use App\Support\ChatActor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A thread was created, renamed, opened or closed: participants refresh their
 * conversation list. Personal actor channels are used (rather than only the
 * conversation channel) because a brand-new thread has no subscribers yet on
 * its own channel.
 */
class ChatConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatConversation $conversation) {}

    public function broadcastAs(): string
    {
        return 'chat-conversation-updated';
    }

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        $channels = [];

        foreach (ChatParticipant::query()->where('conversation_id', $this->conversation->id)->get() as $participant) {
            if ($key = $participant->actorKey()) {
                $channels[] = new PrivateChannel(ChatActor::channelName($key));
            }
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'type' => $this->conversation->type,
            'status' => $this->conversation->status,
            'title' => $this->conversation->title,
        ];
    }
}
