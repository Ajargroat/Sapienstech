<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateDirectThreadRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Requests\Chat\StoreGroupRequest;
use App\Http\Requests\Chat\UpdateConversationRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Student;
use App\Models\User;
use App\Support\ChatActor;
use App\Support\ChatJs;
use App\Support\ChatService;
use App\Support\StudentAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultant chat workspace (گفتگوی مستقیم).
 *
 * A consultant may have unlimited direct threads (one per student) and any
 * number of group rooms; every thread is owned by exactly one staff actor.
 * Tenant admins see the same API as staff — they only differ in that studio
 * settings are theirs to write (see the settings controller).
 *
 * Tenant isolation: {conversation} and {message} bind through models carrying
 * the BelongsToTenant scope, so foreign-tenant rows 404 before this class
 * runs; membership on top of that is asserted inside ChatService (404 — same
 * code as "not found", so no existence leaks).
 */
class ChatController extends Controller
{
    private function actor(): ChatActor
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return ChatActor::make($user);
    }

    // =====================================================================
    // Page
    // =====================================================================

    public function index(): View
    {
        $actor = $this->actor();

        return view('consultant.chat.index', [
            'chat' => ChatJs::bootPayload($actor, 'consultant'),
        ]);
    }

    // =====================================================================
    // Conversation lists / creation
    // =====================================================================

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

    /** Students picker for the "new conversation" dialog. */
    public function students(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $students = StudentAccess::scope(Student::query(), $this->actor()->model)
            ->when($search !== '',
                fn ($q) => $q->where(fn ($names) => $names
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'grade', 'avatar']);

        return response()->json([
            'students' => $students->map(fn (Student $s) => [
                'id' => (int) $s->id,
                'name' => $s->name,
                'grade' => $s->grade,
                'avatar' => $s->avatar ? tenant_asset($s->avatar) : null,
            ])->values(),
        ]);
    }

    public function store(CreateDirectThreadRequest $request): JsonResponse
    {
        $actor = $this->actor();

        $student = Student::find((int) $request->validated('student_id'));
        abort_unless($student !== null, 404);

        $conversation = ChatService::directThread($actor->model, $student);

        return response()->json(
            ['conversation' => ChatService::conversationPayload($conversation, $actor)],
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }

    public function storeGroup(StoreGroupRequest $request): JsonResponse
    {
        $actor = $this->actor();

        $conversation = ChatService::createGroup(
            $actor->model,
            (string) $request->validated('title'),
            (array) $request->validated('student_ids')
        );

        return response()->json(
            ['conversation' => ChatService::conversationPayload($conversation, $actor)],
            201
        );
    }

    public function show(ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();
        $payload = ChatService::conversationPayload($conversation, $actor);

        return response()->json([
            'conversation' => $payload,
            'page' => ChatService::messagePage($conversation, $actor),
        ]);
    }

    public function update(UpdateConversationRequest $request, ChatConversation $conversation): JsonResponse
    {
        $actor = $this->actor();
        ChatService::assertMember($conversation, $actor);

        // Only the owning consultant (or a tenant admin) renames/closes threads.
        abort_unless(
            $actor->model->isTenantAdmin() || (int) $conversation->staff_user_id === $actor->id(),
            403
        );

        $changes = $request->validated();

        if (($changes['status'] ?? null) === 'closed' && ! ChatService::config('close_threads', true)) {
            unset($changes['status']);
        }

        if (isset($changes['title']) && $conversation->isGroup()) {
            $conversation->title = trim((string) $changes['title']);
        }

        if (isset($changes['status'])) {
            $conversation->status = $changes['status'];
        }

        $conversation->save();

        ChatService::conversationChanged($conversation);

        return response()->json(['conversation' => ChatService::conversationPayload($conversation, $actor)]);
    }

    // =====================================================================
    // Messages
    // =====================================================================

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
        $actor = $this->actor();
        ChatService::deleteMessage($message, $actor);

        return response()->json(['success' => true]);
    }

    // =====================================================================
    // Read state / typing / badge
    // =====================================================================

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
