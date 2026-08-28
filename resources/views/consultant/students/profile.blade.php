@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>پروفایل دانش‌آموز</h1>
            <p>
                <a href="{{ route('consultant.dashboard') }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> بازگشت به لیست دانش‌آموزان
                </a>
            </p>
        </div>
    </div>

    <section class="panel student-profile-panel">
        <div class="student-profile-head">
            <span class="student-avatar-lg">{{ mb_substr($student->name, 0, 1) }}</span>
            <div>
                <h2>{{ $student->name }}</h2>
                <span class="student-email">{{ $student->email }}</span>
            </div>
        </div>

        <div class="student-profile-grid">
            <div class="profile-field">
                <span class="profile-field-label">پایه</span>
                <span class="profile-field-value">{{ $student->grade ?? '—' }}</span>
            </div>
            <div class="profile-field">
                <span class="profile-field-label">جنسیت</span>
                <span class="profile-field-value">{{ $student->gender ?? '—' }}</span>
            </div>
            <div class="profile-field">
                <span class="profile-field-label">رشته</span>
                <span class="profile-field-value">{{ $student->major ?? '—' }}</span>
            </div>
        </div>

        <div class="student-profile-links">
            <a href="{{ route('consultant.student.report-card', $student) }}" class="secondary-button">
                <i class="fas fa-chart-line"></i> کارنامه
            </a>
            <a href="{{ route('consultant.student.exams', $student) }}" class="secondary-button">
                <i class="fas fa-tasks"></i> آزمون‌ها
            </a>
            <a href="{{ route('consultant.student.schedule', $student) }}" class="secondary-button">
                <i class="fas fa-calendar-alt"></i> برنامه
            </a>
            <a href="{{ route('consultant.student.source-permissions', $student) }}" class="secondary-button">
                <i class="fas fa-shield-alt"></i> دسترسی منابع
            </a>
        </div>
    </section>
@endsection
