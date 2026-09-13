<?php

namespace App\Events\Chat;

use App\Models\ChatConversation;
use App\Support\ChatActor;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An actor read up to message #N in a thread: drives the sender's receipt
 * ticks (conversation channel) and the reader's own badge reset (their
 * personal actor channel).
 */
class ChatRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatActor $actor,
        public int $lastReadId,
    ) {}

    public function broadcastAs(): string
    {
        return 'chat-read';
    }

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel(ChatConversation::channelName((int) $this->conversation->id))];

        // Tell the author's other devices their badge is clear for this thread.
        $channels[] = new PrivateChannel(ChatActor::channelName($this->actor->key()));

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'actor_key' => $this->actor->key(),
            'last_read_id' => $this->lastReadId,
        ];
    }
}
