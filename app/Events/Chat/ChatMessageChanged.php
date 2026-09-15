<?php

namespace App\Events\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message was edited or deleted — subscribers re-render (edited) or remove
 * (deleted) the row in place instead of refetching the page. Deletions are
 * permanent, so `message` is null in that case and `message_id` carries the
 * row to drop.
 */
class ChatMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ChatMessage $message,
        public string $action, // edited | deleted
    ) {}

    public function broadcastAs(): string
    {
        return 'chat-message-changed';
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(ChatConversation::channelName((int) $this->message->conversation_id))];
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'conversation_id' => (int) $this->message->conversation_id,
            'message_id' => (int) $this->message->id,
            'message' => $this->message->fresh()?->toPayloadArray(),
        ];
    }
}
