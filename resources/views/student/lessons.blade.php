{{--
    Student portal — lessons library: published study files for the
    student's grade, grouped by subject, one clean download action per file.
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-profile-head">
        <span class="student-avatar-lg"><i class="fas fa-book-open-reader"></i></span>
        <div>
            <h2>درس‌ها و منابع آموزشی</h2>
            <span class="student-email">جزوه‌ها و فایل‌های کلاس‌های شما</span>
        </div>
    </section>

    <div class="student-today">
        <span class="student-today-label">فایل‌ها</span>
        <span>{{ persian_digits($total) }} مورد</span>
    </div>
</div>

@if($bySubject->isNotEmpty())
    <div class="student-side">
        @foreach($bySubject as $subject => $group)
            <section class="panel student-panel">
                <header class="student-panel-head">
                    <h2><i class="fas fa-book"></i> {{ $subject }}</h2>
                    <span class="count-badge">{{ persian_digits($group->count()) }} فایل</span>
                </header>

                <div class="exam-grid" data-stagger>
                    @foreach($group as $material)
                        <article class="exam-card">
                            <div class="exam-card-icon exam-card-icon--quiz">
                                <i class="fas fa-file-pdf teacher-material-icon"></i>
                                <span class="exam-card-icon-label">{{ $material->formattedSize() }}</span>
                            </div>
                            <div class="exam-card-body">
                                <h3 class="exam-card-title">{{ $material->title }}</h3>
                                <ul class="exam-card-facts">
                                    <li><i class="fas fa-user-tie"></i>{{ $material->teacher?->name ?? '—' }}</li>
                                    <li>
                                        <i class="far fa-clock"></i>
                                        <time class="fa-date" datetime="{{ $material->created_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($material->created_at->format('Y/m/d')) }}</time>
                                    </li>
                                </ul>
                                @if($material->description)
                                    <p class="teacher-material-desc">{{ $material->description }}</p>
                                @endif
                                <div class="teacher-material-actions">
                                    <a href="{{ route('student.lessons.download', $material) }}" class="primary-button">
                                        <i class="fas fa-download"></i> دانلود
                                    </a>
                                    @if($material->grade === null)
                                        <span class="student-tag student-tag-grade">همه پایه‌ها</span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@else
    <div class="panel student-panel">
        <div class="empty-state">
            <i class="far fa-folder-open"></i>
            <h3>هنوز فایلی منتشر نشده</h3>
            <p>به‌محض انتشار جزوه‌های کلاس، اینجا می‌توانید دانلودشان کنید.</p>
        </div>
    </div>
@endif

@vite(['resources/js/features/student-dashboard.js'])
@endsection
