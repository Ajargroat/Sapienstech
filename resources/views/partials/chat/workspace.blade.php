{{--
    Chat workspace shell — shared by the consultant and student portals
    (resources/views/consultant/chat/index.blade.php and
    resources/views/student/chat/index.blade.php).

    $chat — boot payload from App\Support\ChatJs::bootPayload():
            me / routes / config / transport / strings / csrf.

    All message + conversation rendering is done by
    resources/js/features/direct-chat.js against these data-* hooks, from the
    same JSON shapes the API returns in both transports (REST and broadcast).
    The server-rendered markup stays static so the tenancy/theme layers
    (CSS variables, RTL, font) apply exactly like every other panel.

    $chat['me']['is_staff'] gates the consultant-only controls (new thread,
    new group, close/reopen, rename) — the API enforces the same rules again.
--}}
@php
    $isStaff = $chat['me']['is_staff'];
    $strings = $chat['strings'];
    $cfg     = $chat['config'];
    $rtl     = site('tenant.direction', 'rtl') !== 'ltr';
@endphp

<div class="chat-workspace"
     data-chat-root
     data-chat-boot='@json($chat)'>

    {{-- ============================== List pane ============================== --}}
    <aside class="chat-list" data-chat-list aria-label="{{ $strings['direct_chat'] }}">
        <header class="chat-list-head">
            <h1 class="chat-list-title">{{ $strings['direct_chat'] }}</h1>

            @if($isStaff)
                <div class="chat-list-actions">
                    @if($cfg['groups']['enabled'])
                        <button type="button" class="chat-icon-btn" data-chat-open-group
                                title="{{ $strings['chat_new_group'] }}"
                                aria-label="{{ $strings['chat_new_group'] }}">
                            <i class="fas fa-users" aria-hidden="true"></i>
                        </button>
                    @endif
                    <button type="button" class="chat-icon-btn chat-icon-btn--primary" data-chat-open-direct
                            title="{{ $strings['chat_new_conversation'] }}"
                            aria-label="{{ $strings['chat_new_conversation'] }}">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        </header>

        <div class="chat-search">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search"
                   data-chat-list-search
                   placeholder="{{ $strings['chat_search_hint'] }}"
                   aria-label="{{ $strings['chat_search_hint'] }}">
        </div>

        <div class="chat-list-body" data-chat-conversations role="list">
            <div class="chat-skeleton" aria-hidden="true">
                @for($i = 0; $i < 4; $i++)
                    <div class="chat-skeleton-row"></div>
                @endfor
            </div>
        </div>
    </aside>

    {{-- ============================== Thread pane ============================== --}}
    <section class="chat-thread" data-chat-thread hidden>
        <header class="chat-thread-head">
            <button type="button" class="chat-icon-btn chat-back" data-chat-back
                    aria-label="بازگشت به فهرست گفتگوها">
                <i class="fas fa-arrow-{{ $rtl ? 'right' : 'left' }}" aria-hidden="true"></i>
            </button>

            <div class="chat-peer" data-chat-peer>
                <span class="chat-avatar chat-avatar--head" data-chat-peer-avatar></span>
                <span class="chat-peer-text">
                    <strong class="chat-peer-name" data-chat-peer-name></strong>
                    <span class="chat-peer-sub" data-chat-peer-sub></span>
                </span>
            </div>

            <div class="chat-thread-actions">
                <span class="chat-status-chip" data-chat-status-chip hidden>{{ $strings['chat_closed'] }}</span>

                @if($isStaff && $cfg['close_threads'])
                    <button type="button" class="chat-icon-btn" data-chat-toggle-status hidden
                            title="{{ $strings['chat_close_thread'] }}"
                            aria-label="{{ $strings['chat_close_thread'] }}">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </button>
                @endif
                @if($isStaff)
                    <button type="button" class="chat-icon-btn" data-chat-rename hidden
                            title="تغییر نام گروه" aria-label="تغییر نام گروه">
                        <i class="fas fa-pen" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
        </header>

        <div class="chat-messages-scroller" data-chat-scroller>
            <button type="button" class="chat-load-older" data-chat-older hidden>
                پیام‌های قدیمی‌تر
            </button>
            <ol class="chat-messages" data-chat-messages role="log" aria-live="polite"></ol>
        </div>

        <div class="chat-typing" data-chat-typing hidden>
            <span class="chat-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <span data-chat-typing-name></span>
            <span>{{ $strings['chat_typing'] }}</span>
        </div>

        <footer class="chat-composer" data-chat-composer>
            @if($cfg['attachments']['enabled'])
                {{-- Chip tray: direct-chat.js renders one chip per pending file. --}}
                <div class="chat-attachment-strip" data-chat-pending hidden></div>
            @endif

            <div class="chat-composer-row">
                <button type="button" class="chat-icon-btn chat-emoji-btn" data-chat-emoji-btn
                        title="ایموجی" aria-label="انتخاب ایموجی">
                    <i class="far fa-face-smile" aria-hidden="true"></i>
                </button>
                @if($cfg['attachments']['enabled'])
                    <button type="button" class="chat-icon-btn" data-chat-attach-btn
                            title="{{ $strings['chat_attach'] }}"
                            aria-label="{{ $strings['chat_attach'] }}">
                        <i class="fas fa-paperclip" aria-hidden="true"></i>
                    </button>
                    <input type="file"
                           data-chat-attach
                           multiple
                           accept="{{ implode(',', array_filter([
                               in_array('image', $cfg['attachments']['types'], true) ? 'image/*' : null,
                               in_array('pdf', $cfg['attachments']['types'], true) ? 'application/pdf' : null,
                           ])) }}"
                           hidden>
                @endif

                {{-- contenteditable so picked emoji render as Apple images
                     inline (a plain <textarea> would show OS glyphs and,
                     worse, Chromium coalesces consecutive emoji in RTL text).
                     The serialized payload stays plain Unicode. --}}
                <div class="chat-input"
                     data-chat-input
                     contenteditable="true"
                     role="textbox"
                     aria-multiline="true"
                     data-max-length="{{ $cfg['message_max_length'] }}"
                     data-placeholder="{{ $cfg['placeholder'] }}"
                     aria-label="{{ $cfg['placeholder'] }}"></div>

                <button type="button" class="chat-send" data-chat-send aria-label="{{ $strings['chat_send'] }}">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                </button>

                <div class="chat-emoji-pop" data-chat-emoji-pop hidden role="dialog" aria-label="ایموجی"></div>
            </div>
        </footer>

        <div class="chat-readonly-banner" data-chat-readonly hidden>
            <i class="fas fa-lock" aria-hidden="true"></i>
            <span>{{ $strings['chat_closed'] }}</span>
        </div>
    </section>

    {{-- ============================== Empty state ============================== --}}
    <div class="chat-empty" data-chat-empty>
        <span class="chat-empty-icon"><i class="fas fa-comments" aria-hidden="true"></i></span>
        @if(!empty($cfg['greeting']))
            <p class="chat-empty-greeting">{{ $cfg['greeting'] }}</p>
        @endif
        <p class="chat-empty-text">{{ $cfg['empty_text'] }}</p>
    </div>

    @if($isStaff)
        {{-- ==================== New direct thread dialog ==================== --}}
        <dialog class="chat-dialog" data-chat-direct-modal>
            <div class="chat-dialog-form">
                <h2 class="chat-dialog-title">{{ $strings['chat_new_conversation'] }}</h2>
                <div class="chat-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" data-chat-direct-search
                           placeholder="جستجوی دانش‌آموز…" aria-label="جستجوی دانش‌آموز">
                </div>
                <div class="chat-dialog-list" data-chat-direct-results role="list"></div>
                <div class="chat-dialog-actions">
                    <button type="button" class="chat-btn chat-btn--ghost" data-chat-close-dialog>انصراف</button>
                </div>
            </div>
        </dialog>

        {{-- ======================= New group dialog ======================= --}}
        <dialog class="chat-dialog" data-chat-group-modal>
            <div class="chat-dialog-form" data-chat-group-form>
                <h2 class="chat-dialog-title">{{ $strings['chat_new_group'] }}</h2>
                <label class="chat-field">
                    <span>{{ $strings['chat_group_name'] }}</span>
                    <input type="text" class="chat-field-input" data-chat-group-title maxlength="120">
                </label>
                <p class="chat-field-label">{{ $strings['chat_pick_students'] }}</p>
                <div class="chat-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" data-chat-group-search placeholder="جستجو…" aria-label="جستجوی دانش‌آموز">
                </div>
                <div class="chat-dialog-list chat-dialog-list--check" data-chat-group-results></div>
                <p class="chat-dialog-error" data-chat-group-error hidden></p>
                <div class="chat-dialog-actions">
                    <button type="button" class="chat-btn chat-btn--ghost" data-chat-close-dialog>انصراف</button>
                    <button type="button" class="chat-btn chat-btn--primary" data-chat-group-create>
                        {{ $strings['chat_new_group'] }}
                    </button>
                </div>
            </div>
        </dialog>

        {{-- ======================= Rename dialog ======================= --}}
        <dialog class="chat-dialog" data-chat-rename-modal>
            <div class="chat-dialog-form">
                <h2 class="chat-dialog-title">تغییر نام گروه</h2>
                <label class="chat-field">
                    <span>{{ $strings['chat_group_name'] }}</span>
                    <input type="text" class="chat-field-input" data-chat-rename-input maxlength="120">
                </label>
                <div class="chat-dialog-actions">
                    <button type="button" class="chat-btn chat-btn--ghost" data-chat-close-dialog>انصراف</button>
                    <button type="button" class="chat-btn chat-btn--primary" data-chat-rename-save>ذخیره</button>
                </div>
            </div>
        </dialog>
    @endif
</div>

@vite(['resources/js/features/direct-chat.js'])
