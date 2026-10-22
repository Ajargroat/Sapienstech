{{--
    The staff lesson surface's layout shim.

    The same controller set renders under BOTH /consultant/* and /teacher/*,
    and those two panels have different shells. The views never name a prefix
    (every link uses lessons_route()), so the only thing that has to differ per
    panel is which layout wraps the page — and that is the one thing this file
    decides, from the current route name.
--}}
@php
    $panel = \App\Support\Lessons\LessonAccess::currentPanel() ?? lessons_prefix();
@endphp
@extends($panel === 'teacher' ? 'layouts.teacher' : 'layouts.consultant')

@section('content')
    <div class="lessons-surface" data-lessons-panel="{{ $panel }}">
        <h1 class="lessons-surface-title">@yield('lesson-title')</h1>

        @yield('lesson-content')
    </div>

    @stack('lesson-scripts')
@endsection
