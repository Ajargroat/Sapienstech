<?php

namespace App\Support;

/**
 * The boot payload consumed by resources/js/features/direct-chat.js.
 *
 * One shape for both portals (URL templates with an ``__ID__`` placeholder,
 * tenant-resolved chat config, and the realtime transport descriptor) so the
 * browser module never needs portal-specific constants and the whole feature
 * stays configurable through the site() layers without touching JS.
 */
class ChatJs
{
    public static function bootPayload(ChatActor $actor, string $portal = 'consultant'): array
    {
        $prefix = $portal === 'student' ? 'student.direct-chat.' : 'consultant.direct-chat.';

        $url = fn (string $name, array $params = []) => route($prefix.$name, $params);

        return [
            'me' => [
                'key' => $actor->key(),
                'name' => $actor->name(),
                'avatar' => $actor->avatar() ? tenant_asset($actor->avatar()) : null,
                'is_staff' => $actor->isStaff(),
                'is_admin' => $actor->isStaff() && $actor->model->isTenantAdmin(),
            ],
            'routes' => [
                'conversations' => $url('conversations'),
                'conversation' => $url('conversations.show', ['conversation' => '__ID__']),
                'messages' => $url('conversations.messages', ['conversation' => '__ID__']),
                'send' => $url('conversations.messages.store', ['conversation' => '__ID__']),
                'read' => $url('conversations.read', ['conversation' => '__ID__']),
                'typing' => $url('conversations.typing', ['conversation' => '__ID__']),
                'message' => $portal === 'consultant' ? $url('messages.update', ['message' => '__ID__']) : $url('messages.update', ['message' => '__ID__']),
                'unread' => $url('unread'),
                'create' => $portal === 'consultant' ? $url('conversations.store') : null,
                'group' => $portal === 'consultant' ? $url('groups.store') : null,
                'students' => $portal === 'consultant' ? $url('students') : null,
                'updateConversation' => $portal === 'consultant' ? $url('conversations.update', ['conversation' => '__ID__']) : null,
            ],
            'config' => self::config(),
            'transport' => self::transport(),
            'strings' => self::strings(),
            'csrf' => csrf_token(),
        ];
    }

    /** Tenant-label dictionary slice for the chat UI (RTL, per-tenant copy). */
    public static function strings(): array
    {
        $keys = [
            'direct_chat', 'chat_new_conversation', 'chat_new_group', 'chat_search_hint',
            'chat_empty_list', 'chat_pick_students', 'chat_group_name', 'chat_closed',
            'chat_open_thread', 'chat_close_thread', 'chat_typing', 'chat_delete_message',
            'chat_edit_message', 'chat_saved', 'chat_attach', 'chat_send',
        ];

        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = (string) site("labels.{$key}", $key);
        }

        return $strings;
    }

    /** The tenant-resolved chat knobs, with defaults materialized. */
    public static function config(): array
    {
        return [
            'enabled' => ChatService::enabled(),
            'message_max_length' => max(100, (int) ChatService::config('message_max_length', 4000)),
            'read_receipts' => (bool) ChatService::config('read_receipts', true),
            'typing_indicator' => (bool) ChatService::config('typing_indicator', true),
            'attachments' => [
                'enabled' => (bool) ChatService::config('attachments.enabled', true),
                'max_kb' => max(1, (int) ChatService::config('attachments.max_kb', 5120)),
                'types' => (array) ChatService::config('attachments.types', ['image', 'pdf']),
            ],
            'groups' => [
                'enabled' => (bool) ChatService::config('groups.enabled', true),
            ],
            'edit_window_minutes' => (int) ChatService::config('edit_window_minutes', ChatService::EDIT_WINDOW_MINUTES),
            'placeholder' => (string) ChatService::config('placeholder_text', 'پیام خود را بنویسید…'),
            'empty_text' => (string) ChatService::config('empty_text', 'هنوز پیامی نیست.'),
            'greeting' => ChatService::config('greeting_text'),
            'poll' => [
                'thread' => max(1500, (int) ChatService::config('poll_interval_ms.thread', 4000)),
                'list' => max(5000, (int) ChatService::config('poll_interval_ms.list', 15000)),
                'badge' => max(10000, (int) ChatService::config('poll_interval_ms.badge', 30000)),
            ],
            'close_threads' => (bool) ChatService::config('close_threads', true),
        ];
    }

    /**
     * Realtime transport descriptor. Non-null only when the app is actually
     * configured for a websocket broadcaster (Laravel Reverb or Pusher):
     * installing Reverb and switching BROADCAST_CONNECTION is all it takes to
     * upgrade every chat page from polling to push — the JS uses WS when this
     * is set and the poll intervals above otherwise.
     */
    public static function transport(): ?array
    {
        $driver = (string) config('broadcasting.default', 'null');

        if (! in_array($driver, ['reverb', 'pusher'], true)) {
            return null;
        }

        $connection = (array) config("broadcasting.connections.{$driver}", []);

        $key = $connection['key'] ?? null;

        if (! $key) {
            return null;
        }

        if ($driver === 'reverb') {
            $options = (array) ($connection['options'] ?? []);

            return [
                'driver' => 'reverb',
                'key' => $key,
                'host' => $options['host'] ?? (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
                'port' => (int) ($options['port'] ?? 8080),
                'scheme' => $options['scheme'] ?? 'http',
                'auth_endpoint' => '/broadcasting/auth',
            ];
        }

        $options = (array) ($connection['options'] ?? []);

        return [
            'driver' => 'pusher',
            'key' => $key,
            'host' => $options['host'] ?? null,
            'port' => (int) ($options['port'] ?? 443),
            'scheme' => 'https',
            'cluster' => $options['cluster'] ?? 'mt1',
            'auth_endpoint' => '/broadcasting/auth',
        ];
    }
}
