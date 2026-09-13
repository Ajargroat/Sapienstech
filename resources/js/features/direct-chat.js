/*
 * Direct chat (گفتگوی مستقیم) — shared by the consultant and student
 * portals. The Blade shell (resources/views/partials/chat/workspace.blade.php)
 * stays static; everything inside the data-* hooks is rendered by this module
 * from the JSON the API returns, so REST responses and broadcast payloads
 * drive the exact same code path.
 *
 * Transport:
 *   - With a websocket broadcaster configured (Reverb/Pusher), the private
 *     conversation + actor channels push events instantly, and a slow
 *     resync poll covers reconnect gaps.
 *   - Without one, the same state machine polls at the tenant-configured
 *     chat.poll_interval_ms cadences. Either way the feature is fully
 *     functional; upgrading to push is a config change, not a code change.
 *
 * pusher-js is loaded lazily from a CDN only when a WS transport is
 * configured, so the chat bundle never hard-depends on it and stays build-
 * safe in environments without npm installs.
 */

const BOOT_ATTR = 'data-chat-boot';

function el(tag, cls, text) {
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
}

const nearBottom = (node) =>
    node.scrollHeight - node.scrollTop - node.clientHeight < 140;

function timeText(iso) {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return new Intl.DateTimeFormat(
            document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-GB',
            { hour: '2-digit', minute: '2-digit' }
        ).format(d);
    } catch {
        return '';
    }
}

function dayText(iso) {
    if (!iso) return '';
    try {
        const date = new Date(iso);
        const now = new Date();
        const sameDay = (a, b) => a.toDateString() === b.toDateString();
        if (sameDay(date, now)) return 'امروز';
        const yest = new Date(now);
        yest.setDate(now.getDate() - 1);
        if (sameDay(date, yest)) return 'دیروز';
        return new Intl.DateTimeFormat(
            document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-GB',
            { year: 'numeric', month: 'long', day: 'numeric' }
        ).format(date);
    } catch {
        return '';
    }
}

export default function init() {
    const root = document.querySelector('[data-chat-root]');
    if (!root || root.dataset.chatBooted) return;
    root.dataset.chatBooted = '1';

    let boot;
    try {
        boot = JSON.parse(root.getAttribute(BOOT_ATTR) || '{}');
    } catch {
        return;
    }

    const { me, routes, config, transport, strings, csrf } = boot;

    // ------------------------------------------------------------------
    // Element hooks
    // ------------------------------------------------------------------
    const q = (sel) => root.querySelector(sel);
    const listSearch = q('[data-chat-list-search]');
    const conversationsBox = q('[data-chat-conversations]');
    const thread = q('[data-chat-thread]');
    const emptyState = q('[data-chat-empty]');
    const scroller = q('[data-chat-scroller]');
    const messagesBox = q('[data-chat-messages]');
    const olderBtn = q('[data-chat-older]');
    const typingRow = q('[data-chat-typing]');
    const typingName = q('[data-chat-typing-name]');
    const composer = q('[data-chat-composer]');
    const input = q('[data-chat-input]');
    const sendBtn = q('[data-chat-send]');
    const attachBtn = q('[data-chat-attach-btn]');
    const attachInput = q('[data-chat-attach]');
    const pendingStrip = q('[data-chat-pending]');
    const readonlyBanner = q('[data-chat-readonly]');
    const peerName = q('[data-chat-peer-name]');
    const peerSub = q('[data-chat-peer-sub]');
    const peerAvatar = q('[data-chat-peer-avatar]');
    const statusChip = q('[data-chat-status-chip]');
    const toggleStatusBtn = q('[data-chat-toggle-status]');
    const renameBtn = q('[data-chat-rename]');
    const backBtn = q('[data-chat-back]');

    // ------------------------------------------------------------------
    // State
    // ------------------------------------------------------------------
    const state = {
        conversations: [],
        active: null,            // conversation payload of the open thread
        page: { has_more: false, next_before_id: null },
        pendingFiles: [],      // queue of not-yet-sent File objects
        pendingUrls: new Map(),// File -> object URL for chip thumbnails
        editing: null,           // message object being edited
        ws: null,
        wsSubscriptions: {},     // channelName -> channel
        timers: {},
        typingAt: 0,
        typingExpireTimer: null,
        destroyed: false,
    };

    // ------------------------------------------------------------------
    // HTTP helpers
    // ------------------------------------------------------------------
    // The boot-embedded token goes stale whenever another tab logs in
    // (session()->regenerate() rotates it). The XSRF-TOKEN cookie is re-set
    // on every server response, so read it live and send it as the
    // X-XSRF-TOKEN header — Laravel decrypts that one server-side.
    function liveCsrfToken() {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    async function api(url, options = {}) {
        const opts = {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.json ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {}),
            },
        };
        if (options.method && options.method !== 'GET') {
            const live = liveCsrfToken();
            if (live) opts.headers['X-XSRF-TOKEN'] = live;
            else opts.headers['X-CSRF-TOKEN'] = csrf;
        }
        if (options.json) opts.body = JSON.stringify(options.json);
        else if (options.form) opts.body = options.form;
        if (options.method) opts.method = options.method;

        let response = await fetch(url, opts);

        // Stale token: any response re-issues the cookie — refresh via a cheap
        // GET and replay the request once.
        if (response.status === 419 && !options._csrfRetry) {
            try { await fetch(routes.unread, { credentials: 'same-origin' }); } catch { /* ignore */ }
            return api(url, { ...options, _csrfRetry: 1 });
        }

        let data = null;
        try { data = await response.json(); } catch { /* non-JSON */ }

        if (!response.ok) {
            const message =
                (data && (data.message || (data.errors && Object.values(data.errors)[0]?.[0]))) ||
                'خطا در ارتباط با سرور.';
            const error = new Error(message);
            error.status = response.status;
            throw error;
        }
        return data;
    }

    const pathFor = (template, id) => template.replace('__ID__', String(id));

    function toast(message, isError = true) {
        let box = root.querySelector('.chat-toast');
        if (!box) {
            box = el('div', 'chat-toast');
            box.setAttribute('role', 'status');
            root.appendChild(box);
        }
        box.textContent = message;
        box.classList.toggle('is-error', isError);
        box.classList.add('is-visible');
        clearTimeout(box._hide);
        box._hide = setTimeout(() => box.classList.remove('is-visible'), 3500);
    }

    // ------------------------------------------------------------------
    // Conversation list
    // ------------------------------------------------------------------
    function avatarInto(node, name, url) {
        node.textContent = '';
        if (url) {
            const img = el('img');
            img.src = url;
            img.alt = name || '';
            img.loading = 'lazy';
            node.appendChild(img);
        } else {
            node.textContent = (name || '؟').trim().charAt(0) || '؟';
        }
    }

    function conversationNode(c) {
        const item = el('button', 'chat-item');
        item.type = 'button';
        item.dataset.id = c.id;
        item.setAttribute('role', 'listitem');
        if (state.active && state.active.id === c.id) item.classList.add('is-active');

        const avatar = el('span', 'chat-avatar');
        if (c.type === 'group') {
            const icon = el('i', 'fas fa-users');
            avatar.appendChild(icon);
        } else {
            avatar.textContent = (c.title || '؟').trim().charAt(0) || '؟';
        }
        item.appendChild(avatar);

        const body = el('span', 'chat-item-body');
        const line1 = el('span', 'chat-item-line');
        line1.appendChild(el('strong', 'chat-item-name', c.title));
        line1.appendChild(el('span', 'chat-item-time', c.last_message ? timeText(c.last_message.created_at) : ''));
        body.appendChild(line1);

        const line2 = el('span', 'chat-item-line chat-item-line--muted');
        const preview = c.last_message
            ? (c.last_message.attachment && !c.last_message.body ? '📎 ' + (c.last_message.attachment.name || 'فایل')
                : c.last_message.body)
            : (c.status === 'closed' ? '' : config.empty_text);
        line2.appendChild(el('span', 'chat-item-preview', preview || ''));
        if (c.status === 'closed') line2.appendChild(el('span', 'chat-item-lock fas fa-lock'));
        body.appendChild(line2);
        item.appendChild(body);

        if (c.unread > 0) item.appendChild(el('span', 'chat-item-badge', c.unread > 99 ? '۹۹+' : String(c.unread)));

        item.addEventListener('click', () => openConversation(c.id));
        return item;
    }

    function renderList() {
        conversationsBox.textContent = '';
        if (!state.conversations.length) {
            conversationsBox.appendChild(el('p', 'chat-list-empty', strings.chat_empty_list));
            return;
        }
        state.conversations.forEach((c) => conversationsBox.appendChild(conversationNode(c)));
    }

    function syncShellBadge(total) {
        document.querySelectorAll('[data-chat-unread-badge]').forEach((badge) => {
            badge.textContent = total > 99 ? '۹۹+' : String(total);
            badge.hidden = total === 0;
        });
    }

    async function loadConversations() {
        const search = listSearch?.value?.trim();
        const data = await api(routes.conversations + (search ? `?search=${encodeURIComponent(search)}` : ''));
        state.conversations = data.conversations || [];
        renderList();
        syncShellBadge(data.unread_total || 0);
        refreshEmptyState();
    }

    function bumpListItem(conversationId, patch) {
        const c = state.conversations.find((x) => x.id === conversationId);
        if (!c) { loadConversations().catch(() => {}); return; }
        Object.assign(c, patch);
        // Move to top.
        state.conversations = [c, ...state.conversations.filter((x) => x.id !== conversationId)];
        renderList();
    }

    function refreshEmptyState() {
        const hasActive = Boolean(state.active);
        thread.hidden = !hasActive;
        if (emptyState) emptyState.hidden = hasActive;
    }

    // ------------------------------------------------------------------
    // Thread rendering
    // ------------------------------------------------------------------

    // Telegram-style receipt ticks, rendered ONLY on your own bubbles.
    // The icon is a small state machine:
    //   clock         — still in flight (POST not confirmed by the server)
    //   single check  — saved on the server, not read yet
    //   double check  — at least one other participant read it; brightened
    //                   once every other participant has read it. In a
    //                   direct thread there is exactly one reader, so "read"
    //                   is immediately the bright double check.
    function othersCount() {
        const ps = state.active?.participants;
        return ps && ps.length > 1 ? ps.length - 1 : 1;
    }

    function tickClasses(m) {
        if (m.pending) return 'fa-clock';
        const others = othersCount();
        const count = Math.min(Math.max(0, m.seen_count || 0), others);
        return `${count > 0 ? 'fa-check-double' : 'fa-check'}${count >= others ? ' is-seen' : ''}`;
    }

    // Re-derive the whole icon from the message state instead of toggling
    // individual classes: a clock→check→double-check transition must never
    // leave two state classes (i.e. two glyphs) on the element at once.
    function setTicks(icon, m) {
        icon.classList.remove('fa-clock', 'fa-check', 'fa-check-double', 'is-seen');
        for (const cls of tickClasses(m).split(' ')) icon.classList.add(cls);
    }

    // --------------------------- Photo lightbox --------------------------
    // Telegram-style viewer: tapping a photo opens it full-screen in an
    // overlay instead of a new tab; clicking anywhere (or pressing Esc)
    // dismisses it. One overlay node is created lazily and reused.
    let lightbox = null;

    function closePhotoLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('is-open');
        document.body.classList.remove('chat-lightbox-open');
    }

    function openPhotoLightbox(url, caption) {
        if (!lightbox) {
            lightbox = el('div', 'chat-lightbox');
            lightbox.setAttribute('role', 'dialog');
            lightbox.setAttribute('aria-modal', 'true');
            lightbox.appendChild(el('span', 'chat-lightbox-close fas fa-xmark'));
            const figure = el('figure', 'chat-lightbox-figure');
            const img = el('img', 'chat-lightbox-img');
            const cap = el('p', 'chat-lightbox-caption');
            figure.append(img, cap);
            lightbox.appendChild(figure);
            lightbox.addEventListener('click', closePhotoLightbox);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closePhotoLightbox();
            });
            document.body.appendChild(lightbox);
        }
        const img = lightbox.querySelector('.chat-lightbox-img');
        img.src = url;
        img.alt = caption || '';
        const cap = lightbox.querySelector('.chat-lightbox-caption');
        cap.innerHTML = '';
        if (caption) renderEmojiText(cap, caption);
        cap.hidden = !caption;
        // Nudge the element so the fade/zoom transition re-runs even when
        // the overlay is reopened back-to-back.
        lightbox.classList.remove('is-open');
        void lightbox.offsetWidth;
        lightbox.classList.add('is-open');
        document.body.classList.add('chat-lightbox-open');
    }

    function attachmentNode(attachment, caption) {
        const a = el('a', 'chat-attachment');
        a.href = attachment.url;
        a.rel = 'noopener';

        if ((attachment.mime || '').startsWith('image/')) {
            // Photos carry no filename line; the bubble itself is the image.
            a.title = attachment.name || '';
            const img = el('img', 'chat-attachment-image');
            img.src = attachment.url;
            img.alt = attachment.name || '';
            img.loading = 'lazy';
            img.decoding = 'async';
            a.appendChild(img);
            // Ctrl/shift/alt/middle-click still falls through to the
            // browser's native "open link" behaviour.
            a.addEventListener('click', (e) => {
                if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                e.preventDefault();
                openPhotoLightbox(attachment.url, caption);
            });
            return a;
        }

        a.target = '_blank';

        const meta = el('span', 'chat-attachment-meta');
        meta.appendChild(el('i', 'fas fa-file-pdf'));
        meta.appendChild(el('span', null, attachment.name || 'فایل'));
        if (attachment.size) {
            meta.appendChild(el('span', 'chat-attachment-size', `(${formatBytes(attachment.size)})`));
        }
        a.appendChild(meta);
        return a;
    }

    function messageNode(m) {
        const row = el('li', 'chat-row');
        row.dataset.id = m.id;
        if (m.type === 'system') {
            row.classList.add('is-system');
            row.appendChild(el('span', 'chat-system-pill', m.body));
            return row;
        }

        const mine = m.sender && m.sender.key === me.key;
        row.classList.add(mine ? 'is-mine' : 'is-theirs');

        if (!mine && state.active?.type === 'group') {
            const avatar = el('span', 'chat-avatar chat-avatar--tiny');
            avatarInto(avatar, m.sender?.name, m.sender?.avatar);
            row.appendChild(avatar);
        }

        const bubble = el('div', 'chat-bubble');
        if (m.pending) bubble.classList.add('is-pending');

        const attach = m.attachment;
        // An image takes the whole bubble (media card), so its footer is an
        // overlay instead of a strip of bubble background under the photo.
        const isMedia = Boolean(attach) && (attach.mime || '').startsWith('image/');
        if (isMedia) bubble.classList.add('chat-bubble--media');

        if (!mine && m.sender && state.active?.type === 'group') {
            bubble.appendChild(el('span', 'chat-bubble-author', m.sender.name));
        }

        let text = null;
        if (m.body) {
            text = el('p', 'chat-bubble-text');
            renderEmojiText(text, m.body);
        }
        const attachNode = attach ? attachmentNode(attach, m.body) : null;

        if (isMedia) {
            // Image on top, its caption underneath — one message, one card.
            if (attachNode) bubble.appendChild(attachNode);
            if (text) bubble.appendChild(text);
        } else {
            if (text) bubble.appendChild(text);
            if (attachNode) bubble.appendChild(attachNode);
        }

        const foot = el('span', 'chat-bubble-foot');
        if (m.edited) foot.appendChild(el('span', 'chat-bubble-edited', strings.chat_saved));
        foot.appendChild(el('time', 'chat-bubble-time', timeText(m.created_at)));
        if (mine && config.read_receipts) {
            const ticks = el('i', `fas chat-bubble-ticks ${tickClasses(m)}`);
            ticks.setAttribute('aria-hidden', 'true');
            foot.appendChild(ticks);
        }
        if (mine && !m.pending) {
            const actions = el('span', 'chat-bubble-actions');
            if (m.body) {
                const editB = el('button', 'chat-bubble-btn fas fa-pen');
                editB.type = 'button';
                editB.title = strings.chat_edit_message;
                editB.addEventListener('click', () => startEdit(m));
                actions.appendChild(editB);
            }
            const delB = el('button', 'chat-bubble-btn fas fa-trash');
            delB.type = 'button';
            delB.title = strings.chat_delete_message;
            delB.addEventListener('click', () => deleteMessage(m));
            actions.appendChild(delB);
            if (actions.children.length) {
                if (isMedia) {
                    // The footer is a closed pill over the photo, so the
                    // hover actions move out to the opposite corner.
                    actions.classList.add('chat-bubble-actions--overlay');
                    bubble.appendChild(actions);
                } else {
                    foot.appendChild(actions);
                }
            }
        }
        bubble.appendChild(foot);
        row.appendChild(bubble);
        return row;
    }

    function appendMessage(m, autoscroll = true) {
        const stick = !autoscroll || nearBottom(scroller);
        messagesBox.appendChild(messageNode(m));
        addDaySeparators();
        if (stick) scroller.scrollTop = scroller.scrollHeight;
    }

    function prependMessages(list) {
        const stickTop = scroller.scrollTop;
        const height = scroller.scrollHeight;
        const frag = document.createDocumentFragment();
        list.forEach((m) => frag.appendChild(messageNode(m)));
        messagesBox.prepend(frag);
        addDaySeparators();
        scroller.scrollTop = stickTop + (scroller.scrollHeight - height);
    }

    function addDaySeparators() {
        messagesBox.querySelectorAll('.chat-day-sep').forEach((n) => n.remove());
        let lastDay = null;
        messagesBox.querySelectorAll('.chat-row').forEach((row) => {
            const id = Number(row.dataset.id);
            const list = activeMessages();
            const m = list.find((x) => x.id === id);
            const day = m ? dayText(m.created_at) : null;
            if (day && day !== lastDay) {
                const sep = el('li', 'chat-day-sep', day);
                messagesBox.insertBefore(sep, row);
            }
            lastDay = day || lastDay;
        });
    }

    function activeMessages() {
        return (state.messagesByConv && state.messagesByConv[state.active?.id]) || [];
    }

    // ------------------------------------------------------------------
    // Open / load a thread
    // ------------------------------------------------------------------
    let loadingConversation = 0;

    async function openConversation(id) {
        if (loadingConversation === id) return;
        loadingConversation = id;
        stopTypingIndicators();

        try {
            const data = await api(pathFor(routes.conversation, id));
            if (state.destroyed || loadingConversation !== id) return;

            state.active = data.conversation;
            state.messagesByConv = state.messagesByConv || {};
            state.messagesByConv[id] = data.page.messages.slice();
            state.page = data.page;

            renderThreadHeader();
            messagesBox.textContent = '';
            activeMessages().forEach((m) => messagesBox.appendChild(messageNode(m)));
            addDaySeparators();
            olderBtn.hidden = !state.page.has_more;
            scroller.scrollTop = scroller.scrollHeight;

            renderList();
            refreshEmptyState();
            root.classList.add('has-thread');

            if (me.is_staff) updateThreadActions();

            markReadIfHidden(id);
            subscribeConversation(id);
            startThreadPoll();
        } catch (e) {
            if (e.status === 404) {
                toast('گفتگو پیدا نشد.');
                loadConversations().catch(() => {});
            } else {
                toast(e.message);
            }
        } finally {
            // Reset the re-entry guard; otherwise re-opening the same
            // conversation after "back" silently no-ops.
            if (loadingConversation === id) loadingConversation = 0;
        }
    }

    function renderThreadHeader() {
        const c = state.active;
        peerName.textContent = c.title;
        const parts = [];
        if (c.type === 'group' && c.participant_count) parts.push(`${c.participant_count} نفر`);
        if (c.type !== 'group' && c.participants) {
            parts.push(c.participants.find((p) => p.key !== me.key)?.name || '');
        }
        peerSub.textContent = parts.filter(Boolean).join(' · ');

        peerAvatar.textContent = '';
        if (c.type === 'group') {
            peerAvatar.appendChild(el('i', 'fas fa-users'));
        } else {
            const peer = (c.participants || []).find((p) => p.key !== me.key);
            avatarInto(peerAvatar, peer?.name || c.title, peer?.avatar);
        }

        statusChip.hidden = c.status !== 'closed';
        readonlyBanner.hidden = c.status !== 'closed';
        composer.hidden = c.status === 'closed';
    }

    function updateThreadActions() {
        const c = state.active;
        if (!c || !me.is_staff) return;
        if (toggleStatusBtn) {
            const own = c.participants && c.participants.some((p) => p.key === me.key && p.role === 'owner');
            toggleStatusBtn.hidden = !(own && config.close_threads);
            toggleStatusBtn.title = c.status === 'closed' ? strings.chat_open_thread : strings.chat_close_thread;
            toggleStatusBtn.querySelector('i')?.classList.toggle('fa-unlock', c.status === 'closed');
            toggleStatusBtn.querySelector('i')?.classList.toggle('fa-lock', c.status !== 'closed');
        }
        if (renameBtn) renameBtn.hidden = c.type !== 'group';
    }

    async function loadOlder() {
        if (!state.active || !state.page.has_more) return;
        olderBtn.disabled = true;
        try {
            const data = await api(
                pathFor(routes.messages, state.active.id) + `?before_id=${state.page.next_before_id}`
            );
            state.page = data;
            state.messagesByConv[state.active.id] = data.messages.concat(activeMessages());
            prependMessages(data.messages);
            olderBtn.hidden = !data.has_more;
        } catch (e) {
            toast(e.message);
        } finally {
            olderBtn.disabled = false;
        }
    }

    // ------------------------------------------------------------------
    // Read state
    // ------------------------------------------------------------------
    async function markReadNow() {
        const c = state.active;
        if (!c) return;
        try {
            const data = await api(pathFor(routes.read, c.id), { method: 'POST', json: {} });
            const conv = state.conversations.find((x) => x.id === c.id);
            if (conv) conv.unread = 0;
            syncShellBadge(data.unread_total || 0);
            renderList();
        } catch { /* non-fatal */ }
    }

    function markReadIfHidden() {
        const c = state.active;
        if (!c) return;
        const conv = state.conversations.find((x) => x.id === c.id);
        if (conv && conv.unread > 0) {
            debouncedMarkRead();
        }
    }

    let markReadTimer = null;
    function debouncedMarkRead() {
        clearTimeout(markReadTimer);
        markReadTimer = setTimeout(markReadNow, 900);
    }

    // ------------------------------------------------------------------
    // Composer
    // ------------------------------------------------------------------
    // The composer is a contenteditable div so picked emoji render as the
    // same Apple images the picker shows (a plain <textarea> can only use
    // OS glyphs, and Chromium coalesces consecutive emoji inside RTL text,
    // making them overlap). Serialization stays plain Unicode: every emoji
    // <img> carries its raw characters in `alt`, so what the server sees is
    // identical to what a textarea would have submitted.
    const maxLen = () => Number(input?.dataset.maxLength) || config.message_max_length || 4000;

    let lastCaretRange = null;
    function saveCaret() {
        const sel = window.getSelection();
        if (sel && sel.rangeCount) {
            const r = sel.getRangeAt(0);
            if (input.contains(r.startContainer)) lastCaretRange = r.cloneRange();
        }
    }

    function serializeInput() {
        let out = '';
        const walk = (node) => {
            node.childNodes.forEach((child) => {
                if (child.nodeType === Node.TEXT_NODE) out += child.nodeValue;
                else if (child.nodeName === 'BR') out += '\n';
                else if (child.nodeName === 'IMG') out += child.alt || '';
                else if (child.nodeType === Node.ELEMENT_NODE) {
                    // Browsers may wrap lines in block elements after odd
                    // edits; treat them as newline boundaries.
                    if (/^(DIV|P)$/.test(child.nodeName) && out && !out.endsWith('\n')) out += '\n';
                    walk(child);
                }
            });
        };
        walk(input);
        return out;
    }

    function setInputText(text) {
        input.textContent = '';
        const str = String(text || '').slice(0, maxLen());
        if (str) renderEmojiText(input, str);
        autoGrow();
    }

    function caretToEnd() {
        const range = document.createRange();
        range.selectNodeContents(input);
        range.collapse(false);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    function setComposerMode(m) {
        state.editing = m || null;
        composer.classList.toggle('is-editing', Boolean(m));
        if (m) {
            setInputText(m.body || '');
            input.focus();
            caretToEnd();
        }
    }

    function startEdit(m) {
        if (!m || !m.body) return;
        const limit = config.edit_window_minutes;
        if (limit > 0 && m.created_at) {
            const ageMinutes = (Date.now() - new Date(m.created_at).getTime()) / 60000;
            if (ageMinutes > limit) {
                toast('امکان ویرایش این پیام وجود ندارد.');
                return;
            }
        }
        setComposerMode(m);
    }

    function autoGrow() {
        // scrollHeight never includes the element's borders while a
        // border-box `height` does, so copying it verbatim left the box
        // 2px shorter than its own content — which made the vertical
        // scrollbar appear even on a single line. Add the borders back.
        const box = getComputedStyle(input);
        const border = box.boxSizing === 'border-box'
            ? (parseFloat(box.borderTopWidth) || 0) + (parseFloat(box.borderBottomWidth) || 0)
            : 0;
        input.style.height = 'auto';
        const natural = input.scrollHeight + border;
        input.classList.toggle('is-full', natural > 160);
        const height = Math.min(natural, 160);
        input.style.height = Math.max(height, parseFloat(box.minHeight) || 0) + 'px';
    }

    async function sendOrSave() {
        if (!state.active) return;
        const body = serializeInput().trim();

        if (state.editing) {
            const target = state.editing;
            if (!body) return;
            try {
                const data = await api(pathFor(routes.message, target.id), { method: 'PUT', json: { body } });
                replaceMessage(data.message);
            } catch (e) {
                toast(e.message);
                return;
            }
            setInputText('');
            setComposerMode(null);
            return;
        }

        const files = state.pendingFiles.slice();
        if (!body && !files.length) return;

        const conversationId = state.active.id;
        setInputText('');
        clearPendingFiles();

        // The schema stores one attachment per message, so multiple files
        // become one message each. The caption rides along with the FIRST
        // file (Telegram) instead of going out as a separate message.
        const queue = files.length
            ? files.map((file, i) => ({ body: i === 0 ? body : null, file }))
            : [{ body, file: null }];

        const now = Date.now();
        const temps = queue.map((item, i) => {
            const temp = {
                id: -(now + i),
                type: 'text',
                body: item.body,
                edited: false,
                sender: { key: me.key, name: me.name, avatar: me.avatar },
                mine: true,
                pending: true,
                created_at: new Date(now + i).toISOString(),
                attachment: item.file
                    ? { name: item.file.name, size: item.file.size, mime: item.file.type, url: URL.createObjectURL(item.file) }
                    : null,
            };
            activeMessages().push(temp);
            appendMessage(temp);
            return temp;
        });

        // Sequential so server-side ordering matches the optimistic one.
        for (let i = 0; i < queue.length; i++) {
            const { body: b, file } = queue[i];
            const temp = temps[i];
            const form = new FormData();
            if (b) form.append('body', b);
            if (file) form.append('attachment', file);
            try {
                const data = await api(pathFor(routes.send, conversationId), { method: 'POST', form });
                replaceMessage(data.message, temp.id);
            } catch (e) {
                removeMessage(temp.id);
                toast(e.message);
                // Return whatever failed to the composer so it can be retried.
                if (file) { state.pendingFiles.unshift(file); renderPendingStrip(); }
                if (b && !serializeInput().trim()) setInputText(b);
            }
        }
    }

    function replaceMessage(m, tempId) {
        const list = activeMessages();
        const idx = list.findIndex((x) => x.id === (tempId ?? m.id));
        if (idx >= 0) {
            list[idx] = m;
            const node = messagesBox.querySelector(`.chat-row[data-id="${tempId ?? m.id}"]`);
            if (node) node.replaceWith(messageNode(m));
        } else {
            list.push(m);
            appendMessage(m, true);
        }
        addDaySeparators();
    }

    function removeMessage(id) {
        const list = activeMessages();
        const idx = list.findIndex((x) => x.id === id);
        if (idx >= 0) list.splice(idx, 1);
        messagesBox.querySelector(`.chat-row[data-id="${id}"]`)?.remove();
    }

    async function deleteMessage(m) {
        if (!window.confirm(strings.chat_delete_message + '؟')) return;
        try {
            // Deletion is permanent: the row (and its attachment file) is
            // dropped server-side; the broadcast removes it everywhere else.
            await api(pathFor(routes.message, m.id), { method: 'DELETE' });
            removeMessage(m.id);
            loadConversations().catch(() => {});
        } catch (e) {
            toast(e.message);
        }
    }

    function clearPendingFiles() {
        state.pendingFiles = [];
        state.pendingUrls.forEach((u) => URL.revokeObjectURL(u));
        state.pendingUrls.clear();
        if (attachInput) attachInput.value = '';
        renderPendingStrip();
    }

    function removePendingFile(idx) {
        const [file] = state.pendingFiles.splice(idx, 1);
        if (file) {
            const url = state.pendingUrls.get(file);
            if (url) { URL.revokeObjectURL(url); state.pendingUrls.delete(file); }
        }
        renderPendingStrip();
    }

    function renderPendingStrip() {
        if (!pendingStrip) return;
        pendingStrip.textContent = '';
        if (!state.pendingFiles.length) { pendingStrip.hidden = true; return; }
        state.pendingFiles.forEach((file, idx) => {
            const chip = el('div', 'chat-pending-chip');
            const isImg = (file.type || '').startsWith('image/');
            const iconWrap = el('span', 'chat-pending-icon');
            if (isImg) {
                let url = state.pendingUrls.get(file);
                if (!url) { url = URL.createObjectURL(file); state.pendingUrls.set(file, url); }
                const thumb = el('img', 'chat-pending-thumb');
                thumb.src = url;
                thumb.alt = '';
                iconWrap.appendChild(thumb);
            } else {
                iconWrap.appendChild(el('i', `fas ${file.type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file'}`));
            }
            chip.appendChild(iconWrap);
            const info = el('span', 'chat-pending-info');
            info.appendChild(el('span', 'chat-attachment-name', file.name));
            info.appendChild(el('span', 'chat-pending-size', formatBytes(file.size)));
            chip.appendChild(info);
            const x = el('button', 'chat-pending-clear');
            x.type = 'button';
            x.setAttribute('aria-label', 'حذف پیوست');
            x.appendChild(el('i', 'fas fa-times'));
            x.addEventListener('click', () => removePendingFile(idx));
            chip.appendChild(x);
            pendingStrip.appendChild(chip);
        });
        pendingStrip.hidden = false;
    }

    const formatBytes = (bytes) => {
        if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1).replace(/\.0$/, '')} مگابایت`;
        return `${Math.max(1, Math.round(bytes / 1024))} کیلوبایت`;
    };

    // ------------------------------------------------------------------
    // Typing indicator
    // ------------------------------------------------------------------
    function pingTyping() {
        if (!config.typing_indicator || !state.active || state.active.status !== 'open') return;
        const now = Date.now();
        if (now - state.typingAt < 3000) return;
        state.typingAt = now;
        api(pathFor(routes.typing, state.active.id), { method: 'POST', json: {} }).catch(() => {});
    }

    function showTyping(name) {
        if (!typingRow) return;
        typingName.textContent = name || '';
        typingRow.hidden = false;
        clearTimeout(state.typingExpireTimer);
        state.typingExpireTimer = setTimeout(() => { typingRow.hidden = true; }, 6000);
    }

    function stopTypingIndicators() {
        clearTimeout(state.typingExpireTimer);
        if (typingRow) typingRow.hidden = true;
    }

    // ------------------------------------------------------------------
    // Apple-style emoji (index, inline images, picker)
    //
    // Data + images come lazily from the emoji-datasource-apple / emoji-mart
    // CDN (same zero-build pattern as pusher-js above): message text gets
    // rendered with Apple emoji textures on every platform, and a themed
    // picker inserts characters at the caret. If the CDN is unreachable the
    // UI degrades to native OS emoji glyphs — nothing breaks.
    // ------------------------------------------------------------------
    const EMOJI_DATA_URL = 'https://cdn.jsdelivr.net/npm/@emoji-mart/data@1.2.1/sets/15/apple.json';
    const EMOJI_IMG_BASE = 'https://cdn.jsdelivr.net/npm/emoji-datasource-apple@16.0.0/img/apple/64/';
    const EMOJI_LS_KEY = 'chat-apple-emoji-v2';
    const EMOJI_FREQ_KEY = 'chat-emoji-freq-v1';
    let emojiIndex = null;   // Map: "1F600" | "1F44D-1F3FB" … -> filename.png
    let emojiList = null;    // [{ch, file, name, keys}] for the picker
    let emojiLoad = null;

    const hexCp = (n) => n.toString(16).toUpperCase();

    function decodeSeq(unified) {
        try {
            return unified.split('-').map((h) => String.fromCodePoint(parseInt(h, 16))).join('');
        } catch {
            return '';
        }
    }

    function buildEmojiMaps(entries) {
        const index = new Map();
        const list = [];
        (entries || []).forEach((e) => {
            (e.skins || []).forEach((s, si) => {
                if (!s || !s.unified) return;
                const file = s.unified.toLowerCase() + '.png';
                const keys = new Set([s.unified.toUpperCase(), s.non_qualified ? s.non_qualified.toUpperCase() : '', s.unified.toUpperCase().replace(/(-FE0F)+/g, '')]);
                keys.forEach((k) => { if (k) index.set(k, file); });
                if (si === 0) {
                    list.push({
                        ch: s.native || decodeSeq(s.unified),
                        file,
                        name: e.name || e.id || '',
                        keys: (e.keywords || []).join(' '),
                        cat: e.cat || '',
                    });
                }
            });
        });
        return { index, list };
    }

    // @emoji-mart/data ships `emojis` as an object keyed by id plus a
    // `categories` array that defines the display order; normalize both
    // shapes (object or plain array) into one ordered entry list.
    function emojiEntriesFrom(data) {
        const raw = data && data.emojis;
        if (!raw) return [];
        const all = Array.isArray(raw) ? raw : Object.values(raw);
        if (!Array.isArray(data.categories) || !data.categories.length) return all;

        const byId = new Map();
        all.forEach((e) => { if (e && e.id) byId.set(e.id, e); });
        const out = [];
        const used = new Set();
        data.categories.forEach((c) => {
            (c.emojis || []).forEach((ref) => {
                const e = typeof ref === 'string' ? byId.get(ref) : ref;
                if (e && e.id && !used.has(e.id)) {
                    used.add(e.id);
                    out.push(Object.assign({}, e, { cat: c.id }));
                }
            });
        });
        all.forEach((e) => { if (e && e.id && !used.has(e.id)) out.push(e); });
        return out;
    }

    function rerenderActiveThread() {
        if (!state.active || !messagesBox) return;
        const stick = nearBottom(scroller);
        messagesBox.textContent = '';
        activeMessages().forEach((m) => messagesBox.appendChild(messageNode(m)));
        addDaySeparators();
        if (stick) scroller.scrollTop = scroller.scrollHeight;
    }

    function ensureEmojiData() {
        if (emojiIndex) return Promise.resolve(emojiIndex);
        if (emojiLoad) return emojiLoad;
        try {
            const cached = JSON.parse(localStorage.getItem(EMOJI_LS_KEY) || 'null');
            if (cached && Array.isArray(cached.e)) {
                const built = buildEmojiMaps(cached.e);
                emojiIndex = built.index;
                emojiList = built.list;
                return Promise.resolve(emojiIndex);
            }
        } catch { /* bad cache */ }
        emojiLoad = fetch(EMOJI_DATA_URL)
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error('emoji data'))))
            .then((data) => {
                const entries = emojiEntriesFrom(data);
                const built = buildEmojiMaps(entries);
                if (!built.list.length) throw new Error('empty emoji set');
                emojiIndex = built.index;
                emojiList = built.list;
                try {
                    localStorage.setItem(EMOJI_LS_KEY, JSON.stringify({
                        e: entries.map((x) => ({
                            id: x.id,
                            name: x.name,
                            cat: x.cat,
                            keywords: (x.keywords || []).slice(0, 5),
                            skins: (x.skins || []).slice(0, 1).map((s) => ({ unified: s.unified, non_qualified: s.non_qualified, native: s.native })),
                        })),
                    }));
                } catch { /* quota */ }
                emojiLoad = null;
                rerenderActiveThread();
                return emojiIndex;
            })
            .catch(() => {
                // Keep nothing cached: the next click retries the CDN.
                emojiLoad = null;
                emojiIndex = null;
                return null;
            });
        return emojiLoad;
    }

    function renderEmojiText(parent, text) {
        if (!emojiIndex || !emojiIndex.size
            || !/\p{Extended_Pictographic}|\p{Regional_Indicator}|[\u{1F3FB}-\u{1F3FF}\u{200D}\u{FE0F}]/u.test(text)) {
            parent.textContent = text;
            return;
        }
        const cps = Array.from(text);
        let buf = '';
        const flush = () => { if (buf) { parent.appendChild(document.createTextNode(buf)); buf = ''; } };
        let i = 0;
        while (i < cps.length) {
            const seq = [];
            let bestFile = null;
            let bestLen = 0;
            for (let j = i; j < cps.length && seq.length < 12; j++) {
                const cp = cps[j].codePointAt(0);
                if (j > i) {
                    const prev = seq[seq.length - 1];
                    const joinable =
                        cp === 0x200D || cp === 0xFE0F || cp === 0x20E3
                        || (cp >= 0x1F3FB && cp <= 0x1F3FF)
                        || (cp >= 0x1F1E6 && cp <= 0x1F1FF && seq.length === 1)
                        || prev === 0x200D;
                    if (!joinable) break;
                }
                seq.push(cp);
                const key = seq.map(hexCp).join('-');
                const file = emojiIndex.get(key) || emojiIndex.get(key.replace(/(-FE0F)+/g, ''));
                if (file && cp !== 0x200D) { bestFile = file; bestLen = seq.length; }
            }
            if (bestFile) {
                flush();
                const img = el('img', 'chat-emoji');
                img.src = EMOJI_IMG_BASE + bestFile;
                img.alt = cps.slice(i, i + bestLen).join('');
                img.loading = 'lazy';
                img.decoding = 'async';
                img.onerror = () => img.replaceWith(document.createTextNode(img.alt));
                parent.appendChild(img);
                i += bestLen;
            } else {
                buf += cps[i];
                i += 1;
            }
        }
        flush();
    }

    // --- picker ---
    function insertAtCaret(text) {
        if (!text) return;
        if (serializeInput().length + text.length > maxLen()) {
            toast(`حداکثر ${maxLen()} نویسه مجاز است.`);
            return;
        }
        input.focus();
        const sel = window.getSelection();
        let range = null;
        if (sel && sel.rangeCount && input.contains(sel.getRangeAt(0).startContainer)) {
            range = sel.getRangeAt(0);
        } else if (lastCaretRange && input.contains(lastCaretRange.startContainer)) {
            range = lastCaretRange.cloneRange();
        }
        if (!range) { range = document.createRange(); range.selectNodeContents(input); range.collapse(false); }
        const holder = document.createElement('div');
        renderEmojiText(holder, text);
        const frag = document.createDocumentFragment();
        while (holder.firstChild) frag.appendChild(holder.firstChild);
        const last = frag.lastChild;
        range.deleteContents();
        range.insertNode(frag);
        if (last && sel) {
            const after = document.createRange();
            if (last.nodeType === Node.TEXT_NODE) after.setStart(last, last.nodeValue.length);
            else after.setStartAfter(last);
            after.collapse(true);
            sel.removeAllRanges();
            sel.addRange(after);
        }
        saveCaret();
        autoGrow();
    }

    function buildEmojiPop(emojiPop) {
        let freq = {};
        try { freq = JSON.parse(localStorage.getItem(EMOJI_FREQ_KEY) || '{}'); } catch { /* ignore */ }

        const searchWrap = el('div', 'chat-emoji-search-wrap');
        const search = el('input', 'chat-emoji-search');
        search.type = 'search';
        search.placeholder = 'جستجوی ایموجی…';
        searchWrap.appendChild(search);
        const grid = el('div', 'chat-emoji-grid');
        emojiPop.appendChild(searchWrap);
        emojiPop.appendChild(grid);

        const cell = (e) => {
            const b = el('button', 'chat-emoji-cell');
            b.type = 'button';
            b.title = e.name;
            // Keep the composer focused so the caret position survives.
            b.addEventListener('mousedown', (ev) => ev.preventDefault());
            const img = el('img');
            img.src = EMOJI_IMG_BASE + e.file;
            img.alt = e.ch;
            img.loading = 'lazy';
            img.decoding = 'async';
            img.onerror = () => { b.classList.add('is-glyph'); b.textContent = e.ch; };
            b.appendChild(img);
            b.addEventListener('click', () => {
                insertAtCaret(e.ch);
                freq[e.file] = (freq[e.file] || 0) + 1;
                try { localStorage.setItem(EMOJI_FREQ_KEY, JSON.stringify(freq)); } catch { /* quota */ }
            });
            grid.appendChild(b);
        };

        const render = (rawFilter) => {
            grid.textContent = '';
            const items = emojiList || [];
            const f = (rawFilter || '').trim().toLowerCase();
            if (!f) {
                const top = items.filter((e) => freq[e.file]).sort((a, b) => freq[b.file] - freq[a.file]).slice(0, 24);
                if (top.length) {
                    grid.appendChild(el('div', 'chat-emoji-cat', 'پراستفاده‌ها'));
                    top.forEach(cell);
                }
                // Group by category (people, nature, …) in the dataset's own
                // order; unknown categories fall into a trailing section.
                const cats = new Map();
                items.forEach((e) => {
                    const key = e.cat || 'other';
                    if (!cats.has(key)) cats.set(key, []);
                    cats.get(key).push(e);
                });
                cats.forEach((list, id) => {
                    grid.appendChild(el('div', 'chat-emoji-cat', EMOJI_CAT_NAMES[id] || 'همه'));
                    list.forEach(cell);
                });
            } else {
                items.filter((e) => e.name.toLowerCase().includes(f) || e.keys.toLowerCase().includes(f)).slice(0, 160).forEach(cell);
            }
        };

        search.addEventListener('input', () => render(search.value));
        render('');
    }

    const EMOJI_CAT_NAMES = {
        people: 'چهره‌ها و افراد',
        nature: 'طبیعت و حیوانات',
        foods: 'غذا و نوشیدنی',
        activity: 'ورزش و فعالیت',
        places: 'سفر و مکان',
        objects: 'اشیاء',
        symbols: 'نمادها',
        flags: 'پرچم‌ها',
    };

    function wireEmojiPicker() {
        const emojiBtn = q('[data-chat-emoji-btn]');
        const emojiPop = q('[data-chat-emoji-pop]');
        if (!emojiBtn || !emojiPop) return;
        let loaded = false;
        let pending = false;
        emojiBtn.addEventListener('mousedown', (ev) => ev.preventDefault());
        emojiBtn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            const toggle = () => { emojiPop.hidden = !emojiPop.hidden; };
            if (loaded) { toggle(); return; }
            if (pending) return;
            pending = true;
            ensureEmojiData().then(() => {
                pending = false;
                if (!emojiList || !emojiList.length) {
                    toast('ایموجی‌ها بارگذاری نشدند؛ دوباره تلاش کنید.');
                    return;
                }
                loaded = true;
                buildEmojiPop(emojiPop);
                toggle();
            });
        });
        emojiPop.addEventListener('click', (ev) => ev.stopPropagation());
        const dismiss = () => { emojiPop.hidden = true; };
        document.addEventListener('click', dismiss);
        state.cleanups = state.cleanups || [];
        state.cleanups.push(() => document.removeEventListener('click', dismiss));
    }

    // ------------------------------------------------------------------
    // Realtime transport
    // ------------------------------------------------------------------
    const dashedKey = (key) => key.replace(':', '-');

    function loadPusher() {
        if (window.Pusher) return Promise.resolve();
        if (!loadPusher._promise) {
            loadPusher._promise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js';
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => { loadPusher._promise = null; reject(new Error('pusher-js load failed')); };
                document.head.appendChild(script);
            });
        }
        return loadPusher._promise;
    }

    async function connectWs() {
        if (!transport || state.ws) return;
        try {
            await loadPusher();
        } catch {
            return; // CDN unreachable — polling covers everything.
        }
        const pusher = new window.Pusher(transport.key, {
            wsHost: transport.host,
            wsPort: transport.port,
            wssPort: transport.port,
            forceTLS: transport.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: transport.auth_endpoint,
            authHeaders: { 'X-CSRF-TOKEN': csrf },
            enableStats: false,
            activityTimeout: 60000,
            pongTimeout: 10000,
        });
        state.ws = pusher;

        pusher.connection.bind('disconnected', startAllPolls);
        pusher.connection.bind('connected', () => {
            // Resync once after (re)connect, then relax timers.
            loadConversations().catch(() => {});
            if (state.active) resyncActive();
            startAllPolls(); // slow resync cadence keeps working offline gaps
        });

        subscribeActorChannel();
    }

    function subscribeChannel(name, handlers) {
        if (!state.ws) return;
        if (state.wsSubscriptions[name]) return;
        const channel = state.ws.subscribe(name);
        Object.entries(handlers).forEach(([event, fn]) => channel.bind(event, fn));
        state.wsSubscriptions[name] = channel;
    }

    function unsubscribeChannel(name) {
        const channel = state.wsSubscriptions[name];
        if (channel && state.ws) {
            state.ws.unsubscribe(name);
            delete state.wsSubscriptions[name];
        }
    }

    function subscribeActorChannel() {
        subscribeChannel(`private-chat.actor.${dashedKey(me.key)}`, {
            'client-chat-message': (payload) => onBackgroundMessage(payload),
            'client-chat-conversation-updated': () => loadConversations().catch(() => {}),
            'client-chat-read': (payload) => {
                if (payload.actor_key === me.key) {
                    const conv = state.conversations.find((x) => x.id === payload.conversation_id);
                    if (conv) { conv.unread = 0; renderList(); }
                }
            },
        });
    }

    function subscribeConversation(id) {
        unsubscribeAllConversationChannels();
        subscribeChannel(`private-chat.conversation.${id}`, {
            'client-chat-message': (payload) => {
                if (payload.conversation_id !== id) return;
                const m = payload.message;
                if (activeMessages().some((x) => x.id === m.id)) return;
                m.mine = m.sender && m.sender.key === me.key;
                if (m.mine && !m.seen) m.seen_count = 0;
                try {
                    appendMessage(m, m.mine);
                    activeMessages().push(m);
                } catch (err) {
                    console.warn('[chat] ws render failed', m?.id, err);
                }
                if (!m.mine && document.visibilityState === 'visible') markReadNow();
            },
            'client-chat-message-changed': (payload) => {
                if (payload.conversation_id !== id) return;
                if (payload.action === 'deleted') {
                    // The message is gone for good (payload.message is null).
                    removeMessage(payload.message_id);
                    loadConversations().catch(() => {});
                    return;
                }
                if (!payload.message) return;
                replaceMessage(payload.message);
            },
            'client-chat-read': (payload) => {
                if (payload.actor_key === me.key) return;
                activeMessages().forEach((m) => {
                    if (!m.mine || m.id > payload.last_read_id) return;
                    // Dedupe per actor so repeat read events for the same
                    // participant cannot inflate the count past "everyone".
                    if (!m._seenBy) m._seenBy = new Set();
                    m._seenBy.add(payload.actor_key);
                    m.seen = true;
                    m.seen_count = Math.max(m.seen_count || 0, m._seenBy.size);
                    const ticks = messagesBox.querySelector(`.chat-row[data-id="${m.id}"] .chat-bubble-ticks`);
                    if (ticks) setTicks(ticks, m);
                });
            },
            'client-chat-typing': (payload) => {
                if (payload.actor_key === me.key) return;
                showTyping(payload.name);
            },
        });
    }

    function unsubscribeAllConversationChannels() {
        Object.keys(state.wsSubscriptions)
            .filter((name) => name.includes('.conversation.'))
            .forEach(unsubscribeChannel);
    }

    function onBackgroundMessage(payload) {
        // Badge/list path for threads that are not open on screen.
        if (state.active && payload.conversation_id === state.active.id && document.visibilityState === 'visible') {
            return; // the conversation channel already handled it
        }
        const m = payload.message;
        if (!m) return;
        bumpListItem(payload.conversation_id, {
            last_message: m,
            updated_at: m.created_at,
            unread: ((state.conversations.find((x) => x.id === payload.conversation_id)?.unread) || 0) + (m.sender?.key === me.key ? 0 : 1),
        });
        const total = state.conversations.reduce((sum, c) => sum + (c.unread || 0), 0);
        syncShellBadge(total);
    }

    // ------------------------------------------------------------------
    // Polling (fallback + resync safety net)
    // ------------------------------------------------------------------
    function clearTimers() {
        Object.values(state.timers).forEach(clearInterval);
        state.timers = {};
    }

    const wsLive = () => state.ws && state.ws.connection?.state === 'connected';

    async function resyncActive() {
        if (!state.active) return;
        try {
            const data = await api(pathFor(routes.messages, state.active.id));
            const known = new Map(activeMessages().map((m) => [m.id, m]));

            data.messages.forEach((m) => {
                const local = known.get(m.id);
                if (!local) {
                    m.mine = m.sender && m.sender.key === me.key;
                    // Render FIRST, then register in the known list: a
                    // message that fails to render must be retried by the
                    // next poll instead of being silently marked as seen.
                    try {
                        appendMessage(m, m.mine);
                        activeMessages().push(m);
                    } catch (err) {
                        console.warn('[chat] failed to render message', m?.id, err);
                    }
                    return;
                }
                if (local.body !== m.body
                    || Boolean(local.seen) !== Boolean(m.seen)
                    || (m.seen_count || 0) > (local.seen_count || 0)) {
                    Object.assign(local, m);
                    try { replaceMessage({ ...m }); } catch (err) { console.warn('[chat] replace failed', err); }
                }
            });

            // Prune rows the server no longer has (permanent deletions whose
            // broadcast this tab missed). Only inside the fetched window:
            // older pages loaded by loadOlder() sit below the page minimum id.
            if (data.messages.length) {
                const fresh = new Set(data.messages.map((x) => x.id));
                const minId = Math.min(...fresh);
                activeMessages()
                    .filter((x) => !x.pending && x.id >= minId && !fresh.has(x.id))
                    .slice()
                    .forEach((x) => removeMessage(x.id));
            }

            if (data.messages.some((m) => !known.has(m.id) && !(m.sender && m.sender.key === me.key))
                && document.visibilityState === 'visible') {
                markReadNow();
            }
        } catch { /* transient */ }
    }

    function startThreadPoll() {
        clearInterval(state.timers.thread);
        const interval = wsLive() ? Math.max(config.poll.list, 30000) : config.poll.thread;
        state.timers.thread = setInterval(() => {
            if (document.visibilityState !== 'visible' || !state.active) return;
            resyncActive().catch(() => {});
        }, interval);
    }

    function startAllPolls() {
        clearInterval(state.timers.list);
        const interval = wsLive() ? Math.max(config.poll.list, 60000) : config.poll.list;
        state.timers.list = setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            loadConversations().catch(() => {});
        }, interval);
    }

    const onVisibility = () => {
        if (document.visibilityState !== 'visible') return;
        loadConversations().catch(() => {});
        resyncActive().catch(() => {});
        markReadIfHidden();
    };
    document.addEventListener('visibilitychange', onVisibility);
    // Separate browser windows keep the tab "visible" but unfocused while the
    // user types elsewhere — refetch on focus too, so replies land promptly.
    window.addEventListener('focus', onVisibility);

    // ------------------------------------------------------------------
    // Dialogs (new direct / new group / rename)
    // ------------------------------------------------------------------
    function openDialog(dialog) { dialog?.showModal?.(); }
    function closeDialog(dialog) { dialog?.close?.(); }

    const directModal = q('[data-chat-direct-modal]');
    const directSearch = q('[data-chat-direct-search]');
    const directResults = q('[data-chat-direct-results]');
    const groupModal = q('[data-chat-group-modal]');
    const groupSearch = q('[data-chat-group-search]');
    const groupTitle = q('[data-chat-group-title]');
    const groupResults = q('[data-chat-group-results]');
    const groupError = q('[data-chat-group-error]');
    const renameModal = q('[data-chat-rename-modal]');
    const renameInput = q('[data-chat-rename-input]');

    let directTimer = null;
    async function searchStudents(target, into, checked) {
        const search = (target.value || '').trim();
        try {
            const data = await api(routes.students + `?search=${encodeURIComponent(search)}`);
            into.textContent = '';
            if (!data.students.length) {
                into.appendChild(el('p', 'chat-dialog-none', 'دانش‌آموزی یافت نشد.'));
                return;
            }
            data.students.forEach((s) => {
                if (checked) {
                    const label = el('label', 'chat-check-row');
                    const box = el('input');
                    box.type = 'checkbox';
                    box.value = s.id;
                    label.appendChild(box);
                    const avatar = el('span', 'chat-avatar chat-avatar--tiny');
                    avatarInto(avatar, s.name, s.avatar);
                    label.appendChild(avatar);
                    label.appendChild(el('span', null, s.name + (s.grade ? ` — ${s.grade}` : '')));
                    into.appendChild(label);
                } else {
                    const row = el('button', 'chat-dialog-row');
                    row.type = 'button';
                    row.setAttribute('role', 'listitem');
                    const avatar = el('span', 'chat-avatar chat-avatar--tiny');
                    avatarInto(avatar, s.name, s.avatar);
                    row.appendChild(avatar);
                    row.appendChild(el('span', 'chat-dialog-row-name', s.name));
                    if (s.grade) row.appendChild(el('span', 'chat-dialog-row-meta', s.grade));
                    row.addEventListener('click', () => {
                        closeDialog(directModal);
                        createDirect(s.id);
                    });
                    into.appendChild(row);
                }
            });
        } catch (e) {
            into.textContent = '';
            into.appendChild(el('p', 'chat-dialog-none', e.message));
        }
    }

    async function createDirect(studentId) {
        try {
            const data = await api(routes.create, { method: 'POST', json: { student_id: studentId } });
            await loadConversations().catch(() => {});
            openConversation(data.conversation.id);
        } catch (e) {
            toast(e.message);
        }
    }

    async function createGroup() {
        const ids = [...groupResults.querySelectorAll('input:checked')].map((i) => Number(i.value));
        const title = (groupTitle.value || '').trim();
        groupError.hidden = true;
        if (!title || !ids.length) {
            groupError.textContent = !title ? strings.chat_group_name + ' الزامی است.' : strings.chat_pick_students + ' را انتخاب کنید.';
            groupError.hidden = false;
            return;
        }
        try {
            const data = await api(routes.group, { method: 'POST', json: { title, student_ids: ids } });
            closeDialog(groupModal);
            groupTitle.value = '';
            await loadConversations().catch(() => {});
            openConversation(data.conversation.id);
        } catch (e) {
            groupError.textContent = e.message;
            groupError.hidden = false;
        }
    }

    async function renameGroup() {
        const title = (renameInput.value || '').trim();
        if (!title || !state.active) return;
        try {
            const data = await api(pathFor(routes.updateConversation, state.active.id), { method: 'PATCH', json: { title } });
            state.active = data.conversation;
            renderThreadHeader();
            closeDialog(renameModal);
            loadConversations().catch(() => {});
        } catch (e) {
            toast(e.message);
        }
    }

    async function toggleStatus() {
        if (!state.active) return;
        const next = state.active.status === 'closed' ? 'open' : 'closed';
        try {
            const data = await api(pathFor(routes.updateConversation, state.active.id), { method: 'PATCH', json: { status: next } });
            state.active = data.conversation;
            renderThreadHeader();
            updateThreadActions();
            loadConversations().catch(() => {});
        } catch (e) {
            toast(e.message);
        }
    }

    // ------------------------------------------------------------------
    // Wiring
    // ------------------------------------------------------------------
    q('[data-chat-open-direct]')?.addEventListener('click', () => {
        directSearch.value = '';
        searchStudents(directSearch, directResults, false);
        openDialog(directModal);
    });
    q('[data-chat-open-group]')?.addEventListener('click', () => {
        groupSearch.value = '';
        groupError.hidden = true;
        searchStudents(groupSearch, groupResults, true);
        openDialog(groupModal);
    });
    directSearch?.addEventListener('input', () => {
        clearTimeout(directTimer);
        directTimer = setTimeout(() => searchStudents(directSearch, directResults, false), 250);
    });
    groupSearch?.addEventListener('input', () => {
        clearTimeout(directTimer);
        directTimer = setTimeout(() => searchStudents(groupSearch, groupResults, true), 250);
    });
    q('[data-chat-group-create]')?.addEventListener('click', createGroup);
    root.querySelectorAll('[data-chat-close-dialog]').forEach((btn) =>
        btn.addEventListener('click', (e) => closeDialog(e.target.closest('dialog')))
    );
    q('[data-chat-rename]')?.addEventListener('click', () => {
        if (!state.active) return;
        renameInput.value = state.active.title || '';
        openDialog(renameModal);
    });
    q('[data-chat-rename-save]')?.addEventListener('click', renameGroup);
    toggleStatusBtn?.addEventListener('click', toggleStatus);

    listSearch?.addEventListener('input', () => {
        clearTimeout(listSearch._t);
        listSearch._t = setTimeout(() => loadConversations().catch(() => {}), 300);
    });

    olderBtn?.addEventListener('click', loadOlder);

    // Overlay-style scrollbars: the thumb only appears while actually
    // scrolling and fades out again after a short pause (the thumb size
    // stays natively proportional to the amount of history). The
    // `.is-scrolling` CSS drives the visuals.
    root.querySelectorAll('.chat-messages-scroller, .chat-list-body').forEach((box) => {
        box.addEventListener('scroll', () => {
            box.classList.add('is-scrolling');
            window.clearTimeout(box._scrollbarTimer);
            box._scrollbarTimer = window.setTimeout(
                () => box.classList.remove('is-scrolling'), 1100);
        }, { passive: true });
    });

    input?.addEventListener('input', () => {
        if (serializeInput().length > maxLen()) {
            setInputText(serializeInput().slice(0, maxLen()));
            caretToEnd();
        }
        // Drop leftover <br>s so the :empty placeholder can show again.
        if (!serializeInput()) input.innerHTML = '';
        saveCaret();
        autoGrow();
        pingTyping();
    });
    input?.addEventListener('keyup', saveCaret);
    input?.addEventListener('mouseup', saveCaret);
    input?.addEventListener('paste', (e) => {
        // Plain text only — never let pasted HTML into the composer.
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        if (text) insertAtCaret(text);
    });
    input?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendOrSave();
        } else if (e.key === 'Enter' && e.shiftKey) {
            e.preventDefault();
            insertAtCaret('\n');
        }
        if (e.key === 'Escape' && state.editing) setComposerMode(null);
    });
    sendBtn?.addEventListener('click', sendOrSave);

    attachBtn?.addEventListener('click', () => attachInput?.click());
    attachInput?.addEventListener('change', () => {
        const picked = Array.from(attachInput.files || []);
        attachInput.value = '';
        if (!picked.length) return;
        const maxKb = config.attachments?.max_kb;
        let rejected = 0;
        picked.forEach((f) => {
            if (maxKb && f.size > maxKb * 1024) { rejected += 1; return; }
            state.pendingFiles.push(f);
        });
        if (rejected) toast(`حجم ${rejected} فایل بیشتر از ${maxKb} کیلوبایت است و رد شد.`);
        renderPendingStrip();
    });

    backBtn?.addEventListener('click', () => {
        state.active = null;
        unsubscribeAllConversationChannels();
        clearInterval(state.timers.thread);
        renderList();
        refreshEmptyState();
        root.classList.remove('has-thread');
    });

    scroller?.addEventListener('scroll', () => {
        if (nearBottom(scroller)) markReadIfHidden();
    });

    // ------------------------------------------------------------------
    // Go
    // ------------------------------------------------------------------
    loadConversations().catch((e) => toast(e.message));
    startAllPolls();
    connectWs().then(() => { startThreadPoll(); startAllPolls(); });
    wireEmojiPicker();
    ensureEmojiData().catch(() => {});

    return () => {
        state.destroyed = true;
        clearTimers();
        (state.cleanups || []).forEach((fn) => fn());
        unsubscribeAllConversationChannels();
        Object.keys(state.wsSubscriptions).forEach(unsubscribeChannel);
        if (state.ws) state.ws.disconnect();
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('focus', onVisibility);
    };
}
