{{--
    Student side of direct chat — the same workspace shell as the consultant
    portal (partials.chat.workspace), student-gated: no thread/group creation,
    no moderation controls. Gated by the student_chat feature flag; rendered
    by App\Http\Controllers\Student\ChatController::index().
--}}
@extends('layouts.student')

@section('content')
    @include('partials.chat.workspace', ['chat' => $chat])
@endsection
