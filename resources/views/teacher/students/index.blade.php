{{--
    Teacher panel — student roster. Searchable, grade-filterable, paginated
    server-side; each row links to the per-student detail page.
--}}
@extends('layouts.teacher')

@section('content')
<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-user-group"></i> دانش‌آموزان مجموعه</h2>
        <span class="count-badge">{{ persian_digits($students->total()) }} نفر</span>
    </header>

    @include('consultant.blog.flash')

    <form method="GET" action="{{ route('teacher.students') }}" class="teacher-filters">
        <input
            type="search"
            name="search"
            value="{{ $search }}"
            placeholder="جستجو بر اساس نام یا ایمیل…"
            class="settings-input"
        >
        <select name="grade" class="settings-input">
            <option value="">همه پایه‌ها</option>
            @foreach($gradeOptions as $gradeValue => $gradeLabel)
                <option value="{{ $gradeValue }}" @selected($grade === $gradeValue)>{{ $gradeLabel }}</option>
            @endforeach
        </select>
        <button type="submit" class="primary-button">جستجو</button>
    </form>

    @if($students->isNotEmpty())
        <div class="teacher-grid" data-stagger>
            @foreach($students as $student)
                <a href="{{ route('teacher.students.show', $student) }}" class="teacher-student-card">
                    <span class="student-avatar-lg student-avatar-sm">{{ mb_substr($student->name, 0, 1) }}</span>
                    <div class="teacher-student-body">
                        <strong>{{ $student->name }}</strong>
                        <span class="teacher-student-email" dir="ltr">{{ $student->email }}</span>
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
                    </div>
                    <i class="fas fa-chevron-left teacher-student-chevron" aria-hidden="true"></i>
                </a>
            @endforeach
        </div>

        @if($students->hasPages())
            <nav class="pager" aria-label="صفحه‌بندی">
                {{ $students->links() }}
            </nav>
        @endif
    @else
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h3>{{ $search || $grade ? 'نتیجه‌ای پیدا نشد' : 'دانش‌آموزی وجود ندارد' }}</h3>
            <p>{{ $search || $grade ? 'دانش‌آموزی مطابق جستجوی شما پیدا نشد.' : 'در حال حاضر دانش‌آموزی برای این مجموعه ثبت نشده است.' }}</p>
        </div>
    @endif
</div>
@endsection
