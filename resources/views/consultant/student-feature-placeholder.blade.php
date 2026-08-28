@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>
                <a href="{{ route('consultant.student.profile', $student) }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> بازگشت به پروفایل {{ $student->name }}
                </a>
            </p>
        </div>
    </div>

    <section class="panel feature-placeholder">
        <div class="feature-placeholder-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h2>{{ $title }}</h2>
        <p>این بخش برای «{{ $student->name }}» به‌زودی راه‌اندازی می‌شود.</p>
    </section>
@endsection
