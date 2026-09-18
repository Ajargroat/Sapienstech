<?php

namespace App\Support;

use App\Events\Chat\ChatConversationUpdated;
use App\Events\Chat\ChatMessageChanged;
use App\Events\Chat\ChatMessageSent;
use App\Events\Chat\ChatRead;
use App\Events\Chat\ChatTyping;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * All chat domain logic in one place: both portals (consultant and student)
 * drive the same service, so tenancy rules, dual-FK actor handling, feature
 * configuration and broadcast fan-out can never drift between sides.
 *
 * Authorization lives here rather than in a Gate policy because the platform
 * has two auth guards (web→User, student→Student) and Gate resolves only the
 * default one; every entry point takes an explicit ChatActor and asserts
 * membership itself (fail-closed aborts).
 *
 * The student↔student-DM rule is structural, not a check: a conversation is
 * either direct (one staff + one student) or a group owned by one staff
 * member with N students. There is no shape in which two students can share
 * a thread without a consultant owning it.
 */
class ChatService
{
    /**
     * Platform rule: a message stays editable for one week. Tenants may
     * shorten (or disable, with 0) this via `chat.edit_window_minutes`.
     */
    public const EDIT_WINDOW_MINUTES = 10080;
    // =====================================================================
    // Configuration (tenant-resolved; see theme.php `chat` block)
    // =====================================================================

    /** Tenant-resolved knob with the effective PHP upload ceiling applied. */
    public static function config(?string $key = null, mixed $default = null): mixed
    {
        $config = (array) site('chat', []);

        if ($key === null) {
            return $config;
        }

        $value = data_get($config, $key, $default);

        if ($key === 'attachments.max_kb') {
            $value = self::effectiveMaxKb((int) $value);
        }

        return $value;
    }

    /** "10080" → "۷ روز"-friendly window label for lifecycle error copy. */
    public static function humanWindow(int $minutes): string
    {
        if ($minutes > 0 && $minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days % 7 === 0 && $days >= 7
                ? ($days === 7 ? 'یک هفته' : intdiv($days, 7).' هفته')
                : ($days === 1 ? 'یک روز' : $days.' روز');
        }

        return $minutes.' دقیقه';
    }

    /**
     * The configured cap, clamped to what PHP can actually accept: uploads
     * above upload_max_filesize (or eating post_max_size) are dropped by PHP
     * before Laravel sees them, which surfaces as a confusing generic error.
     * The UI hint and the server-side validation share this number.
     */
    public static function effectiveMaxKb(int $configured): int
    {
        $toKb = fn (string $value): int => (int) round(match (strtolower(substr(trim($value), -1))) {
            'g' => ((float) $value) * 1048576,
            'm' => ((float) $value) * 1024,
            'k' => ((float) $value),
            default => (float) $value / 1024,
        });

        $limits = [
            $toKb(ini_get('upload_max_filesize') ?: '2M'),
            max(1, $toKb(ini_get('post_max_size') ?: '8M') - 128), // multipart framing headroom
        ];

        return max(1, min($configured > 0 ? $configured : PHP_INT_MAX, ...$limits));
    }

    public static function enabled(): bool
    {
        return (bool) self::config('enabled', true);
    }

    // =====================================================================
    // Conversations
    // =====================================================================

    /**
     * The (or a fresh) direct thread between one staff member and one
     * student, with participant rows and an opening system message.
     */
    public static function directThread(User $staff, Student $student): ChatConversation
    {
        $tenant = tenant();
        abort_unless($tenant && (int) $student->tenant_id === (int) $tenant->id, 404);
        abort_unless(StudentAccess::allows($staff, $student), 404);

        $conversation = ChatConversation::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'type' => ChatConversation::TYPE_DIRECT,
                'staff_user_id' => $staff->id,
                'student_id' => $student->id,
            ],
            ['status' => ChatConversation::STATUS_OPEN]
        );

        if (! $conversation->wasRecentlyCreated) {
            return $conversation;
        }

        $conversation->participants()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'student_id' => null,
            'role' => ChatParticipant::ROLE_OWNER,
            'joined_at' => now(),
        ]);

        $conversation->participants()->create([
            'tenant_id' => $tenant->id,
            'user_id' => null,
            'student_id' => $student->id,
            'role' => ChatParticipant::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $message = self::systemMessage(
            $conversation,
            (string) self::config('system_messages.thread_opened', 'گفتگوی مستقیم شروع شد.')
        );

        self::touchLastMessage($conversation, $message);
        self::announceConversation($conversation);

        return $conversation->refresh();
    }

    /**
     * A group room owned by one consultant; students come from the service's
     * explicit membership — student-initiated groups cannot exist because no
     * route reaches this method for a student actor.
     *
     * @param  list<int>  $studentIds
     */
    public static function createGroup(User $staff, string $title, array $studentIds): ChatConversation
    {
        abort_unless((bool) self::config('groups.enabled', true), 403);

        $tenant = tenant();
        abort_unless($tenant && (int) $staff->tenant_id === (int) $tenant->id, 404);

        $max = max(2, (int) self::config('groups.max_members', 50));
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));

        abort_if($studentIds === [], 422, 'برای ساخت گروه حداقل یک دانش‌آموز انتخاب کنید.');
        abort_if(count($studentIds) > $max, 422, "حداکثر {$max} دانش‌آموز در هر گروه.");

        // Resolve against the supplied staff, not whichever guard happens to be logged in.
        $students = StudentAccess::scope(
            Student::withoutGlobalScope('staff_access')->where('tenant_id', $tenant->id),
            $staff
        )->whereIn('id', $studentIds)->get();

        abort_if(
            $students->count() !== count($studentIds),
            422,
            'برخی دانش‌آموزان معتبر نیستند.'
        );

        $conversation = ChatConversation::create([
            'tenant_id' => $tenant->id,
            'type' => ChatConversation::TYPE_GROUP,
            'title' => trim($title) ?: 'گروه گفتگو',
            'staff_user_id' => $staff->id,
            'student_id' => null,
            'created_by_user_id' => $staff->id,
            'status' => ChatConversation::STATUS_OPEN,
        ]);

        $conversation->participants()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'student_id' => null,
            'role' => ChatParticipant::ROLE_OWNER,
            'joined_at' => now(),
        ]);

        foreach ($students as $student) {
            $conversation->participants()->create([
                'tenant_id' => $tenant->id,
                'user_id' => null,
                'student_id' => $student->id,
                'role' => ChatParticipant::ROLE_MEMBER,
                'joined_at' => now(),
            ]);
        }

        $message = self::systemMessage(
            $conversation,
            (string) self::config('system_messages.group_created', 'گروه گفتگو ایجاد شد.')
        );

        self::touchLastMessage($conversation, $message);
        self::announceConversation($conversation);

        return $conversation->refresh();
    }

    // =====================================================================
    // Listing
    // =====================================================================

    /**
     * Conversations visible to an actor, newest activity first, each with its
     * unread count for this actor. One grouped join query supplies all the
     * per-conversation counts (no N+1).
     *
     * @return Collection<int, array{conversation: ChatConversation, unread: int}>
     */
    public static function conversationList(ChatActor $actor, ?string $search = null): Collection
    {
        $conversations = ChatConversation::query()
            ->where('tenant_id', $actor->model->tenant_id)
            ->forActor($actor)
            ->with(['lastMessage.senderUser', 'lastMessage.senderStudent', 'participants'])
            ->when($search, function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where(fn ($matches) => $matches->where('title', 'like', $like)
                    ->orWhereHas('student', fn ($s) => $s->where('name', 'like', $like))
                    ->orWhereHas('staff', fn ($s) => $s->where('name', 'like', $like)));
            })
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->limit(200)
            ->get()
            ->filter(fn (ChatConversation $c) => self::relationsAllow($c));

        $unreads = self::unreadCounts($actor, $conversations->pluck('id')->all());

        return $conversations->map(fn (ChatConversation $c) => [
            'conversation' => $c,
            'unread' => $unreads[$c->id] ?? 0,
        ]);
    }

    /** Total unread across the actor's conversations (memoized per request). */
    public static array $unreadMemo = [];

    public static function unreadTotal(ChatActor $actor): int
    {
        // Do not memoize authorization: links can be revoked while the actor stays logged in.
        $ids = ChatConversation::query()->where('tenant_id', $actor->model->tenant_id)
            ->forActor($actor)->pluck('id')->all();

        return array_sum(self::unreadCounts($actor, $ids));
    }

    /** Unread inside a single conversation for one actor. */
    public static function unreadTotalFor(ChatConversation $conversation, ChatActor $actor): int
    {
        return self::unreadCounts($actor, [(int) $conversation->id])[$conversation->id] ?? 0;
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, int> conversation_id => unread count
     */
    public static function unreadCounts(ChatActor $actor, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $conversationIds = ChatConversation::query()
            ->where('tenant_id', $actor->model->tenant_id)
            ->forActor($actor)
            ->whereIn('id', $conversationIds)
            ->get()
            ->filter(fn (ChatConversation $c) => self::relationsAllow($c))
            ->pluck('id')->all();

        if ($conversationIds === []) {
            return [];
        }

        $participantColumn = $actor->isStudent() ? 'student_id' : 'user_id';
        $senderColumn = $actor->isStudent() ? 'sender_student_id' : 'sender_user_id';

        $rows = ChatMessage::query()
            ->join('chat_participants as cp', 'cp.conversation_id', '=', 'chat_messages.conversation_id')
            ->whereIn('cp.conversation_id', $conversationIds)
            ->where("cp.{$participantColumn}", $actor->id())
            ->where('chat_messages.type', ChatMessage::TYPE_TEXT)
            ->where(
                fn ($q) => $q->whereNull("chat_messages.{$senderColumn}")
                    ->orWhere("chat_messages.{$senderColumn}", '!=', $actor->id())
            )
            ->where(
                fn ($q) => $q->whereNull('cp.last_read_message_id')
                    ->orWhereColumn('chat_messages.id', '>', 'cp.last_read_message_id')
            )
            ->groupBy('cp.conversation_id')
            ->selectRaw('cp.conversation_id as cid, COUNT(*) as unread')
            ->pluck('unread', 'cid');

        return $rows->map(fn ($v) => (int) $v)->all();
    }

    /** Conversation detail payload: meta + participants + my read state. */
    public static function conversationPayload(ChatConversation $conversation, ChatActor $actor): array
    {
        self::assertMember($conversation, $actor);

        $conversation->load(['participants.user', 'participants.student', 'staff', 'student']);

        $participant = $conversation->participantFor($actor);

        return array_merge($conversation->toListArray($actor, self::unreadTotalFor($conversation, $actor)), [
            'participants' => $conversation->participants->map(
                fn (ChatParticipant $p) => [
                    'key' => $p->actorKey(),
                    'name' => $p->actorName(),
                    'role' => $p->role,
                    'avatar' => ($a = $p->toActor()->avatar()) ? tenant_asset($a) : null,
                ]
            )->values(),
            'my_last_read_id' => $participant?->last_read_message_id ? (int) $participant->last_read_message_id : 0,
            'can_send' => self::canSend($conversation, $actor),
        ]);
    }

    /**
     * One page of messages, oldest→newest, with per-message "seen" derived
     * from the other participants' read markers.
     *
     * @return array{messages: array, has_more: bool, next_before_id: ?int}
     */
    public static function messagePage(
        ChatConversation $conversation,
        ChatActor $actor,
        ?int $beforeId = null,
        ?int $take = null,
        ?string $search = null,
    ): array {
        self::assertMember($conversation, $actor);

        $take = $take ?: min(100, max(10, (int) self::config('page_size', 40)));

        $query = $conversation->messages()
            ->with(['senderUser', 'senderStudent'])
            ->when($search !== null && $search !== '',
                fn ($q) => $q->where('body', 'like', "%{$search}%"))
            ->when($beforeId, fn ($q) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($take + 1);

        $rows = $query->get();

        $hasMore = $rows->count() > $take;
        // Query newest-first for the cursor, but hand the client chronological
        // (oldest-first) rows: the thread renderer and loadOlder() prepend /
        // concat assuming ascending id order.
        $messages = $rows->take($take)->reverse()->values();

        // Other participants' read markers drive the receipt checkmarks.
        $otherReads = $conversation->participants()
            ->when(
                $actor->isStudent(),
                fn ($s) => $s->where(fn ($w) => $w->whereNull('student_id')->orWhere('student_id', '!=', $actor->id())),
                fn ($s) => $s->where(fn ($w) => $w->whereNull('user_id')->orWhere('user_id', '!=', $actor->id()))
            )
            ->pluck('last_read_message_id')
            ->map(fn ($v) => (int) $v);

        $payload = $messages->map(function (ChatMessage $m) use ($actor, $otherReads) {
            $row = $m->toPayloadArray($actor);

            if ($row['mine']) {
                $seenBy = $otherReads->filter(fn (int $r) => $r >= (int) $m->id)->count();
                $row['seen'] = $seenBy > 0;
                $row['seen_count'] = $seenBy;
            }

            return $row;
        })->values();

        return [
            'messages' => $payload->all(),
            'has_more' => $hasMore,
            'next_before_id' => $messages->min('id') ?: null,
        ];
    }

    // =====================================================================
    // Messages
    // =====================================================================

    public static function sendMessage(
        ChatConversation $conversation,
        ChatActor $actor,
        ?string $body,
        ?UploadedFile $attachment = null,
        bool $skipRateLimit = false,
    ): ChatMessage {
        self::assertMember($conversation, $actor);
        self::assertCanSend($conversation, $actor);

        if (! $skipRateLimit) {
            self::assertRateLimit($conversation, $actor);
        }

        $body = $body === null ? null : trim($body);
        $maxLength = max(100, (int) self::config('message_max_length', 4000));

        abort_if(
            ($body === null || $body === '') && ! $attachment,
            422,
            'متن پیام خالی است.'
        );
        abort_if(
            $body !== null && mb_strlen($body) > $maxLength,
            422,
            "حداکثر {$maxLength} کاراکتر در هر پیام."
        );

        $message = new ChatMessage;
        $message->fill([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'type' => ChatMessage::TYPE_TEXT,
            'body' => ($body === null || $body === '') ? null : $body,
        ] + $actor->foreignKeys('sender'));

        if ($attachment) {
            self::assertAttachment($attachment);

            // Metadata must be read BEFORE store(): TenantUploads::store moves
            // the upload out of PHP's temp file, after which getSize()/
            // getRealPath() on the original path stat-fail (RuntimeException).
            $name = $attachment->getClientOriginalName();
            $size = (int) $attachment->getSize();
            $mime = (string) $attachment->getClientMimeType();

            $message->attachment_path = TenantUploads::store($attachment, (string) self::config('attachments.folder', 'chat'));
            $message->attachment_name = $name;
            $message->attachment_size = $size;
            $message->attachment_mime = $mime;
        }

        $message->save();

        self::touchLastMessage($conversation, $message);
        self::dispatchSafely(new ChatMessageSent($message));

        return $message->load(['senderUser', 'senderStudent']);
    }

    public static function editMessage(ChatMessage $message, ChatActor $actor, string $body): ChatMessage
    {
        $conversation = $message->conversation;
        self::assertMember($conversation, $actor);

        abort_unless(self::isAuthor($message, $actor), 403);
        abort_unless((bool) self::config('edit_window_minutes', self::EDIT_WINDOW_MINUTES), 403, 'ویرایش پیام غیرفعال است.');

        $window = (int) self::config('edit_window_minutes', self::EDIT_WINDOW_MINUTES);
        abort_if(
            $window > 0 && now()->diffInMinutes($message->created_at, true) > $window,
            403,
            'مهلت ویرایش ('.self::humanWindow($window).') تمام شده است.'
        );

        $maxLength = max(100, (int) self::config('message_max_length', 4000));
        $body = trim($body);
        abort_if($body === '', 422, 'متن پیام خالی است.');
        abort_if(mb_strlen($body) > $maxLength, 422, "حداکثر {$maxLength} کاراکتر.");

        $message->body = $body;
        $message->edited_at = now();
        $message->save();

        self::dispatchSafely(new ChatMessageChanged($message, 'edited'));

        return $message;
    }

    /**
     * Deletion is permanent for everyone (single shared workspace): the row
     * and its stored attachment are removed outright — no tombstone text.
     * The author may delete within their window; conversation staff and tenant
     * admins moderate any message.
     */
    public static function deleteMessage(ChatMessage $message, ChatActor $actor): int
    {
        $conversation = $message->conversation;
        self::assertMember($conversation, $actor);

        $isAuthor = self::isAuthor($message, $actor);

        if (! $isAuthor) {
            abort_unless(
                $actor->isStaff() && (
                    $actor->model->isTenantAdmin()
                    || (int) $conversation->staff_user_id === $actor->id()
                ),
                403,
                'شما اجازه حذف پیام دیگران را ندارید.'
            );
        }

        $window = self::config('delete_window_minutes', null);
        if ($window !== null && $isAuthor) {
            $window = (int) $window;
            abort_if(
                $window > 0 && now()->diffInMinutes($message->created_at, true) > $window,
                403,
                'مهلت حذف پیام تمام شده است.'
            );
        }

        $id = (int) $message->id;
        $wasLast = (int) $conversation->last_message_id === $id;
        $attachment = $message->attachment_path;

        $message->delete();
        TenantUploads::delete($attachment);

        if ($wasLast) {
            // The row is gone — fall the preview back to the newest
            // remaining message (null when the thread just emptied).
            $next = $conversation->messages()->orderByDesc('id')->first();

            $conversation->forceFill([
                'last_message_id' => $next?->id,
                'last_message_at' => $next?->created_at ?? $conversation->created_at,
            ])->save();

            // List previews across all clients refresh from this broadcast.
            self::announceConversation($conversation);
        }

        self::dispatchSafely(new ChatMessageChanged($message, 'deleted'));

        return $id;
    }

    // =====================================================================
    // Read state / typing
    // =====================================================================

    public static function markRead(ChatConversation $conversation, ChatActor $actor): ?int
    {
        $participant = self::assertMember($conversation, $actor);

        $latest = (int) ($conversation->last_message_id
            ?: $conversation->messages()->max('id')
            ?: 0);

        if ($participant->last_read_message_id && $participant->last_read_message_id >= $latest) {
            return $latest;
        }

        $participant->forceFill([
            'last_read_message_id' => $latest,
            'last_read_at' => now(),
        ])->save();

        unset(self::$unreadMemo[$actor->key()]);

        self::dispatchSafely(new ChatRead($conversation, $actor, $latest));

        return $latest;
    }

    public static function broadcastTyping(ChatConversation $conversation, ChatActor $actor): void
    {
        self::assertMember($conversation, $actor);

        abort_unless((bool) self::config('typing_indicator', true), 403);

        self::dispatchSafely(new ChatTyping($conversation, $actor));
    }

    // =====================================================================
    // Guards
    // =====================================================================

    /**
     * @return ChatParticipant the actor's membership row
     *
     * Membership and current staff/student relations are both required,
     * including when called directly without route binding or a staff guard.
     */
    public static function assertMember(ChatConversation $conversation, ChatActor $actor): ChatParticipant
    {
        abort_unless(
            tenant() && (int) $conversation->tenant_id === (int) tenant()->id
                && (int) $actor->model->tenant_id === (int) $conversation->tenant_id
                && self::relationsAllow($conversation),
            404
        );

        $participant = $actor->participantRow($conversation);
        abort_unless($participant !== null, 404);

        return $participant;
    }

    /**
     * Fail closed for the whole group if any membership has been revoked.
     * Hiding only that member would still expose their history and identity.
     */
    private static function relationsAllow(ChatConversation $conversation): bool
    {
        $staff = $conversation->staff()->first();
        if (! $staff || (int) $staff->tenant_id !== (int) $conversation->tenant_id) {
            return false;
        }

        $studentIds = $conversation->participants()->whereNotNull('student_id')
            ->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($conversation->isDirect()) {
            if ($studentIds->count() !== 1 || $studentIds->first() !== (int) $conversation->student_id) {
                return false;
            }
        } elseif (! $conversation->isGroup() || $studentIds->isEmpty()) {
            return false;
        }

        $students = Student::withoutGlobalScope('staff_access')
            ->where('tenant_id', $conversation->tenant_id)->whereIn('id', $studentIds);

        return StudentAccess::scope($students, $staff)->count() === $studentIds->count();
    }

    public static function canSend(ChatConversation $conversation, ChatActor $actor): bool
    {
        if (! self::enabled()
            || (int) $actor->model->tenant_id !== (int) $conversation->tenant_id
            || ! $actor->participantRow($conversation)
            || ! self::relationsAllow($conversation)) {
            return false;
        }

        if (! $conversation->isOpen()) {
            // Closed threads: staff may reopen-and-write, students are read-only.
            return false;
        }

        return true;
    }

    protected static function assertCanSend(ChatConversation $conversation, ChatActor $actor): void
    {
        abort_unless(self::enabled(), 403, 'گفتگو غیرفعال است.');

        if ($conversation->isClosedFor($actor)) {
            abort(403, 'این گفتگو بسته شده است.');
        }
    }

    protected static function assertRateLimit(ChatConversation $conversation, ChatActor $actor): void
    {
        $perMinute = max(1, (int) self::config('rate_limit_per_minute', 30));
        $key = "chat:send:{$conversation->id}:{$actor->key()}";

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            abort(429, 'خیلی سریع پیام می‌فرستید؛ کمی صبر کنید.');
        }

        RateLimiter::hit($key, 60);
    }

    protected static function assertAttachment(UploadedFile $file): void
    {
        abort_unless((bool) self::config('attachments.enabled', true), 403, 'ارسال فایل غیرفعال است.');

        $maxKb = max(1, (int) self::config('attachments.max_kb', 5120));
        abort_if($file->getSize() > $maxKb * 1024, 422, "حجم فایل نباید بیشتر از {$maxKb} کیلوبایت باشد.");

        $allowed = (array) self::config('attachments.types', ['image', 'pdf']);

        $isImage = str_starts_with((string) $file->getMimeType(), 'image/');
        $isPdf = in_array($file->getMimeType(), ['application/pdf'], true);

        $ok = ($isImage && in_array('image', $allowed, true))
            || ($isPdf && in_array('pdf', $allowed, true));

        abort_unless($ok, 422, 'نوع فایل پشتیبانی نمی‌شود.');
    }

    protected static function isAuthor(ChatMessage $message, ChatActor $actor): bool
    {
        return $actor->isStudent()
            ? (int) $message->sender_student_id === $actor->id()
            : (int) $message->sender_user_id === $actor->id();
    }

    // =====================================================================
    // Internals
    // =====================================================================

    protected static function systemMessage(ChatConversation $conversation, string $body): ChatMessage
    {
        $message = ChatMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'type' => ChatMessage::TYPE_SYSTEM,
            'body' => $body,
            'sender_user_id' => null,
            'sender_student_id' => null,
        ]);

        return $message;
    }

    protected static function touchLastMessage(ChatConversation $conversation, ChatMessage|int $message): void
    {
        $id = $message instanceof ChatMessage ? $message->id : $message;

        $conversation->forceFill([
            'last_message_id' => $id,
            'last_message_at' => now(),
        ])->save();
    }

    public static function announceConversation(ChatConversation $conversation): void
    {
        self::dispatchSafely(new ChatConversationUpdated($conversation));
    }

    /** Public alias used by controllers after rename/close/reopen. */
    public static function conversationChanged(ChatConversation $conversation): void
    {
        self::announceConversation($conversation);
    }

    /**
     * Dispatch a broadcast event without letting a dead websocket server turn
     * into a 500 for the sending user. The polling transport covers delivery
     * either way; the log broadcast driver can never fail like this.
     */
    public static function dispatchSafely(mixed $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('chat broadcast failed: '.$e->getMessage());
        }
    }

    /**
     * Close conversations with no activity for N days, across every tenant.
     * The cutoff is each tenant's own `chat.idle_autoclose_days` (resolved
     * inside the loop, after binding the tenant), so 0 disables per tenant;
     * the platform baseline only decides whether to iterate at all.
     */
    public static function autoCloseIdle(): int
    {
        $count = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$count) {
            app()->instance('tenant', $tenant);
            app(SiteConfig::class)->flush();

            $days = (int) self::config('idle_autoclose_days', 0);

            if ($days < 1) {
                app()->forgetInstance('tenant');
                app(SiteConfig::class)->flush();

                return;
            }

            $cutoff = now()->subDays($days);

            $stale = ChatConversation::query()
                ->where('status', ChatConversation::STATUS_OPEN)
                ->where(function ($q) use ($cutoff) {
                    $q->where(fn ($s) => $s->whereNull('last_message_at')->where('created_at', '<', $cutoff))
                        ->orWhere('last_message_at', '<', $cutoff);
                })
                ->get();

            foreach ($stale as $conversation) {
                $conversation->forceFill(['status' => ChatConversation::STATUS_CLOSED])->save();
                self::systemMessage(
                    $conversation,
                    (string) self::config('system_messages.thread_closed', 'گفتگو به دلیل بی‌فعالیتی بسته شد.')
                );
                $count++;
            }

            // Drop the synthetic tenant binding again so the loop never leaks it.
            app()->forgetInstance('tenant');
            app(SiteConfig::class)->flush();
        });

        return $count;
    }
}
