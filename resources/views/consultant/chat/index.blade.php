{{--
    Consultant direct chat (گفتگوی مستقیم).

    Rendered by App\Http\Controllers\Consultant\ChatController::index() at
    /consultant/direct-chat, behind the tenant-resolved direct_chat flag.
    The workspace shell is shared with the student portal through
    partials.chat.workspace; behavior lives in
    resources/js/features/direct-chat.js and styling in
    resources/css/features/chat.css (imported by app.css).
--}}
@extends('layouts.consultant')

@section('content')
    @include('partials.chat.workspace', ['chat' => $chat])
@endsection
