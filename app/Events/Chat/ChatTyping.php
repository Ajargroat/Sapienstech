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
 * Ephemeral typing pulse: never stored, only relayed to the open thread.
 * The client shows "typing…" for a couple of seconds and lets it expire, so
 * a missed pulse can never leave a stuck indicator.
 */
class ChatTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatActor $actor,
    ) {}

    public function broadcastAs(): string
    {
        return 'chat-typing';
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatConversation::channelName((int) $this->conversation->id))];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => (int) $this->conversation->id,
            'actor_key' => $this->actor->key(),
            'name' => $this->actor->name(),
            'at' => now()->toIso8601String(),
        ];
    }
}
