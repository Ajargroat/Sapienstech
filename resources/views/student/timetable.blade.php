{{--
    Student portal — class timetable: the weekly blocks published for the
    student's grade, one day-section per Persian weekday (Saturday first),
    today highlighted.
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg"><i class="fas fa-calendar-week"></i></span>
        <div>
            <h2>جدول کلاسی هفتگی</h2>
            <span class="student-email">زنگ‌های هفته بر اساس پایه {{ $student->grade ?: 'شما' }}</span>
        </div>
    </section>
</div>

@if($items->isNotEmpty())
    <div class="student-side">
        @foreach($dayLabels as $dayIndex => $dayLabel)
            @php $dayItems = $items->get($dayIndex, collect()); @endphp
            <section class="panel student-panel @if($dayIndex === $todayDay) teacher-today-panel @endif">
                <header class="student-panel-head">
                    <h2><i class="far fa-calendar"></i> {{ $dayLabel }}</h2>
                    @if($dayIndex === $todayDay)
                        <span class="count-badge">امروز</span>
                    @elseif($dayItems->isNotEmpty())
                        <span class="count-badge">{{ persian_digits($dayItems->count()) }} زنگ</span>
                    @endif
                </header>

                @if($dayItems->isNotEmpty())
                    <div class="teacher-class-list">
                        @foreach($dayItems as $item)
                            <div class="teacher-class-row" style="--accent: {{ $item->color ?: 'var(--c-primary)' }}">
                                <span class="teacher-class-time" dir="ltr">
                                    {{ persian_digits($item->start_time->format('H:i')) }} – {{ persian_digits($item->end_time->format('H:i')) }}
                                </span>
                                <div class="teacher-class-body">
                                    <strong>{{ $item->title }}</strong>
                                    <span class="teacher-class-meta">
                                        @if($item->subject)<span><i class="fas fa-book"></i> {{ $item->subject }}</span>@endif
                                        @if($item->room)<span><i class="fas fa-location-dot"></i> {{ $item->room }}</span>@endif
                                        <span><i class="fas fa-user-tie"></i> {{ $item->teacher?->name ?? '—' }}</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="teacher-day-empty">کلاسی ثبت نشده است.</p>
                @endif
            </section>
        @endforeach
    </div>
@else
    <div class="panel student-panel">
        <div class="empty-state">
            <i class="far fa-calendar"></i>
            <h3>جدول کلاسی هنوز منتشر نشده</h3>
            <p>به‌محض ثبت زنگ‌های هفته توسط معلم‌ها، اینجا می‌بینید.</p>
        </div>
    </div>
@endif

@vite(['resources/js/features/student-dashboard.js'])
@endsection
