<?php

use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Chat realtime authorization. Both are private channels; the guards below
| are the same membership rule the REST endpoints apply (App\Support\
| ChatService::assertMember), so the websocket surface can never be wider
| than the HTTP surface.
|
| Two guards authenticate a browser, and /broadcasting/auth resolves only
| the default one — so membership is checked against *every* chat actor the
| session knows about (chat_session_actors()), not the injected $user.
|
| The {conversation} parameter is implicitly route-bound to ChatConversation,
| whose BelongsToTenant global scope makes a foreign tenant's conversation
| unresolvable here (403) before the callback even runs.
|
*/

Broadcast::channel('chat.conversation.{conversation}', function (mixed $user, ChatConversation $conversation): bool {
    foreach (chat_session_actors() as $actor) {
        if ($conversation->participantFor($actor)) {
            return true;
        }
    }

    return false;
});

Broadcast::channel('chat.actor.{key}', function (mixed $user, string $key): bool {
    foreach (chat_session_actors() as $actor) {
        if ($actor->key() === str_replace('-', ':', $key)) {
            return true;
        }
    }

    return false;
});
