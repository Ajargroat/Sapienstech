{{--
    Unread-count badge for a chat nav item. $chatActorKey optionally scopes
    to 'student'; default is the web (consultant) guard. ChatService memoizes
    the count per request, and the query is a single indexed join, so this is
    cheap enough to sit in the shell on every authenticated page.

    direct-chat.js updates any [data-chat-unread-badge] live while the user is
    on the chat page; elsewhere the value is simply fresh from the last load.
--}}
@php
    $forStudent = ($chatActorKey ?? 'web') === 'student';
    $user = $forStudent ? \Illuminate\Support\Facades\Auth::guard('student')->user()
                        : \Illuminate\Support\Facades\Auth::user();
    $flag = $forStudent ? 'features.student_chat' : 'features.direct_chat';

    $unread = 0;
    // No tenant context (or a platform admin session, which never lands on a
    // tenant domain) → skip entirely: the tenant scope would be inert and the
    // count could otherwise span tenants.
    if (tenant() && site($flag, false) && $user) {
        $unread = \App\Support\ChatService::unreadTotal(\App\Support\ChatActor::make($user));
    }
@endphp
<span class="nav-badge" data-chat-unread-badge @if($unread === 0) hidden @endif>{{ persian_digits(min($unread, 99)) }}{{ $unread > 99 ? '+' : '' }}</span>
