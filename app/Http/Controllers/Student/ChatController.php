<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Student;
use App\Support\ChatActor;
use App\Support\ChatJs;
use App\Support\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Student side of direct chat.
 *
 * Students only ever *join* conversations a consultant created — one direct
 * thread with their consultant plus any group rooms the consultant made for
 * them. There is intentionally no create/store endpoint here, and the schema
 * (one staff actor per conversation) makes student↔student threads
 * unrepresentable.
 */
class ChatController extends Controller
{
    private function actor(): ChatActor
    {
        $student = auth('student')->user();
        abort_unless($student instanceof Student, 403);

        return ChatActor::make($student);
    }

    public function index(): View
    {
        return view('student.chat.index', [
            'chat' => ChatJs::bootPayload($this->actor(), 'student'),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $actor = $this->actor();

        $rows = ChatService::conversationList($actor, $request->query('search'));

        return response()->json([
            'conversations' => $rows->map(
                fn (array $row) => $row['conversation']->toListArray($actor, $row['unread'])
            )->values(),
            'unread_total' => array_sum($rows->pluck('unread')->all()),
        ]);
    }

    public function show(ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();

        return response()->json([
            'conversation' => ChatService::conversationPayload($conversation, $actor),
            'page' => ChatService::messagePage($conversation, $actor),
        ]);
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();

        return response()->json(ChatService::messagePage(
            $conversation,
            $actor,
            $request->query('before_id') ? (int) $request->query('before_id') : null,
            null,
            $request->query('search') ? trim((string) $request->query('search')) : null,
        ));
    }

    public function sendMessage(SendMessageRequest $request, ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();

        $message = ChatService::sendMessage(
            $conversation,
            $actor,
            $request->validated('body'),
            $request->attachment()
        );

        return response()->json(['message' => $message->toPayloadArray($actor)], 201);
    }

    public function updateMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $actor = $this->actor();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.max(100, (int) ChatService::config('message_max_length', 4000))],
        ]);

        $message = ChatService::editMessage($message, $actor, (string) $data['body']);

        return response()->json(['message' => $message->toPayloadArray($actor)]);
    }

    public function destroyMessage(ChatMessage $message): JsonResponse
    {
        ChatService::deleteMessage($message, $this->actor());

        return response()->json(['success' => true]);
    }

    public function read(ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();

        return response()->json([
            'last_read_id' => ChatService::markRead($conversation, $actor),
            'unread_total' => ChatService::unreadTotal($actor),
        ]);
    }

    public function unread(): JsonResponse
    {
        return response()->json(['unread_total' => ChatService::unreadTotal($this->actor())]);
    }

    public function typing(ChatConversation $conversation): JsonResponse
    {
        ChatService::broadcastTyping($conversation, $this->actor());

        return response()->json(['success' => true]);
    }
}
