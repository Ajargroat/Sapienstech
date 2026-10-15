@extends('layouts.consultant')

@section('content')
    @if(session('status'))
        <div class="settings-flash settings-flash--success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

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
            <span class="student-email">{{ $student->email }}</span>
        </div>
    </section>

    <div class="student-profile-sections" data-stagger>
        <a href="{{ route('consultant.student.report-card', $student) }}" class="profile-section-card">
            <span class="profile-section-icon"><i class="fas fa-chart-line"></i></span>
            <h3>کارنامه</h3>
            <p>کارنامهٔ آزمون‌های شرکتی (قلم‌چی، ماز، …) و آزمون‌های درون‌ساز {{ $student->name }} را ببینید.</p>
            <span class="profile-section-cta">مشاهده <i class="fas fa-arrow-left"></i></span>
        </a>

        <a href="{{ route('consultant.student.exams', $student) }}" class="profile-section-card">
            <span class="profile-section-icon"><i class="fas fa-tasks"></i></span>
            <h3>آزمون‌ها</h3>
            <p>نتایج و وضعیت آزمون‌های {{ $student->name }} را دنبال کنید.</p>
            <span class="profile-section-cta">مشاهده <i class="fas fa-arrow-left"></i></span>
        </a>

        <a href="{{ route('consultant.student.schedule', $student) }}" class="profile-section-card">
            <span class="profile-section-icon"><i class="fas fa-calendar-alt"></i></span>
            <h3>برنامه</h3>
            <p>برنامه هفتگی و جلسات {{ $student->name }} را مدیریت کنید.</p>
            <span class="profile-section-cta">مشاهده <i class="fas fa-arrow-left"></i></span>
        </a>
    </div>

    {{--
        Roster management, in the student's own profile — the place the user
        asked for. Removal is soft (the row and its history survive; the person
        leaves every list and can no longer log in) and is offered to the tenant
        admin/owner only. The confirmation is a required field on the form, not
        a JS-only dialog, so the server refuses a bare POST too.
    --}}
    @if(site('features.student_join_requests', false) && auth()->user()?->isTenantAdmin())
        <section class="profile-danger" style="margin-top:24px">
            <form method="POST" action="{{ route('consultant.student.remove', $student) }}"
                  data-router="off"
                  onsubmit="return confirm('{{ $student->name }} از فهرست دانش‌آموزان حذف شود؟ سوابق آزمون، برنامه و پرداخت باقی می‌ماند، اما این دانش‌آموز دیگر نمی‌تواند وارد شود.')">
                @csrf
                @method('DELETE')
                <input type="hidden" name="confirm" value="remove">
                <button type="submit" class="profile-danger-btn">
                    <i class="fas fa-user-slash" aria-hidden="true"></i>
                    حذف دانش‌آموز از فهرست
                </button>
            </form>
            <p class="student-panel-note">
                این عملیات اطلاعات را پاک نمی‌کند؛ فقط دانش‌آموز از فهرست خارج می‌شود و امکان ورود
                نخواهد داشت. بازگرداندن از همین صفحه ممکن نیست.
            </p>
        </section>
    @endif

    {{-- After creating a student by hand, this page is the redirect target —
         the pop-up shows the generated password exactly once. --}}
    @include('consultant.students.partials.generated-password-dialog')
@endsection
