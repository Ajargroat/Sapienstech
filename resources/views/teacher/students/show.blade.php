{{--
    Teacher panel — one student's detail: profile tags, this teacher's
    assignments for the student's class with their statuses, and the
    materials the student can see.
--}}
@extends('layouts.teacher')

@section('content')
<a href="{{ route('teacher.students') }}" class="teacher-backlink">
    <i class="fas fa-arrow-right"></i> بازگشت به فهرست دانش‌آموزان
</a>

<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg">{{ mb_substr($student->name, 0, 1) }}</span>
        <div>
            <h2>{{ $student->name }}</h2>
            <div class="student-tags">
                @if($student->grade)
                    <span class="student-tag student-tag-grade">{{ $student->grade }}</span>
                @endif
                @if($student->gender)
                    <span class="student-tag student-tag-gender">{{ $student->gender }}</span>
                @endif
                @if($student->major)
                    <span class="student-tag student-tag-major">{{ $student->major }}</span>
                @endif
            </div>
            <span class="student-email" dir="ltr">{{ $student->email }}</span>
        </div>
    </section>
</div>

<div class="student-side">
    @if(site('features.teacher_assignments', false))
        <section class="panel student-panel">
            <header class="student-panel-head">
                <h2><i class="fas fa-tasks"></i> تکالیف این کلاس</h2>
                @if($assignments->isNotEmpty())
                    <span class="count-badge">{{ persian_digits($assignments->count()) }} تکلیف</span>
                @endif
            </header>

            @if($assignments->isNotEmpty())
                <div class="teacher-class-list">
                    @foreach($assignments as $assignment)
                        @php $status = $assignment->statusFor($student->id); @endphp
                        <a href="{{ route('teacher.assignments.show', $assignment) }}" class="teacher-class-row" style="--accent: var(--c-primary)">
                            <span class="status-pill status-pill--{{ $status }}">{{ $statuses[$status] }}</span>
                            <div class="teacher-class-body">
                                <strong>{{ $assignment->title }}</strong>
                                <span class="teacher-class-meta">
                                    @if($assignment->subject)<span><i class="fas fa-book"></i> {{ $assignment->subject }}</span>@endif
                                    @if($assignment->due_at)
                                        <span><i class="fas fa-calendar-day"></i>
                                            <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="empty-state empty-state--compact">
                    <i class="far fa-tasks"></i>
                    <h3>تکلیفی برای این دانش‌آموز نیست</h3>
                    <p>برای پایه تحصیلی او هنوز تکلیفی نساخته‌اید.</p>
                </div>
            @endif
        </section>
    @endif

    @if(site('features.teacher_materials', false))
        <section class="panel student-panel">
            <header class="student-panel-head">
                <h2><i class="fas fa-book-open-reader"></i> جزوه‌های قابل مشاهده</h2>
                @if($materials->isNotEmpty())
                    <span class="count-badge">{{ persian_digits($materials->count()) }} فایل</span>
                @endif
            </header>

            @if($materials->isNotEmpty())
                <div class="teacher-class-list">
                    @foreach($materials as $material)
                        <span class="teacher-class-row" style="--accent: var(--c-info)">
                            <span class="teacher-class-time"><i class="fas fa-file-pdf"></i></span>
                            <div class="teacher-class-body">
                                <strong>{{ $material->title }}</strong>
                                <span class="teacher-class-meta">
                                    @if($material->subject)<span><i class="fas fa-book"></i> {{ $material->subject }}</span>@endif
                                </span>
                            </div>
                        </span>
                    @endforeach
                </div>
            @else
                <div class="empty-state empty-state--compact">
                    <i class="far fa-folder-open"></i>
                    <h3>جزوه‌ای منتشر نشده</h3>
                    <p>فایلی برای پایه تحصیلی این دانش‌آموز بارگذاری نشده است.</p>
                </div>
            @endif
        </section>
    @endif
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
