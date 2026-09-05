@extends('layouts.consultant')

@section('content')
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
@endsection
