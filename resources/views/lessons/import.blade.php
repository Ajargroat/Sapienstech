{{--
    The staff lesson library — what came OUT of the pipeline.

    Uploading and extracting a book is no longer a tenant action: it lives in
    the platform-admin operations console (/ops/lessons), because it is our
    machinery and it must not depend on a tenant's flag, a staff role, or a
    queue worker somebody happened to start. What a consultant or teacher sees
    here is the tenant-facing half — the lessons themselves and their editor.

    Mounted in BOTH panels, so every link goes through lessons_route() and no
    prefix is ever written here.
--}}
@extends('lessons.layout')

@section('lesson-title', 'کتاب‌ها و درس‌ها')

@section('lesson-content')
@include('consultant.blog.flash')

@if($lessons->isNotEmpty())
    <div class="panel student-panel" data-lesson-lessons>
        <header class="student-panel-head">
            <h2><i class="fas fa-book-open-reader"></i> درس‌ها</h2>
            <span class="count-badge">{{ persian_digits($lessons->count()) }} درس</span>
        </header>

        <div class="lesson-import-list">
            @foreach($lessons as $item)
                @php($lessonLabel = $lessonStatusLabels[$item->status] ?? $item->status)
                <article class="lesson-import-row" data-lesson-status="{{ $item->status }}">
                    <div class="lesson-import-main">
                        <a class="lesson-import-title" href="{{ lessons_route('editor.edit', $item) }}">{{ $item->lesson_title }}</a>
                        <ul class="exam-card-facts">
                            <li><i class="fas fa-book"></i>{{ $item->book_title }}</li>
                            @if($item->chapter_title)
                                <li><i class="fas fa-folder-tree"></i>{{ $item->chapter_title }}</li>
                            @endif
                            <li><i class="fas fa-layer-group"></i>{{ $item->grade ?: 'همه پایه‌ها' }}</li>
                            @if($item->source_page_from)
                                <li>
                                    <i class="fas fa-file-lines"></i>
                                    صفحهٔ {{ persian_digits($item->source_page_from) }}@if($item->source_page_to && $item->source_page_to !== $item->source_page_from) تا {{ persian_digits($item->source_page_to) }}@endif
                                </li>
                            @endif
                        </ul>
                        @if($canPreview)
                            <div class="teacher-material-actions">
                                <a class="primary-button" href="{{ route('student.lesson-reader.show', $item) }}">
                                    <i class="fas fa-eye"></i> پیش‌نمایش در خواننده
                                </a>
                            </div>
                        @endif
                    </div>
                    <span class="lesson-status lesson-status--{{ $item->status }}">{{ $lessonLabel }}</span>
                </article>
            @endforeach
        </div>
    </div>
@endif

@if($lessons->isEmpty())
    <div class="empty-state">
        <i class="far fa-folder-open"></i>
        <h3>هنوز کتاب یا درسی آماده نشده</h3>
        <p>کتاب‌ها از کنسول عملیات بارگذاری و استخراج می‌شوند و درس‌های ساخته‌شده اینجا نمایش داده می‌شوند.</p>
    </div>
@endif

@endsection

@push('lesson-scripts')
    @vite(['resources/js/features/consultant-lesson-import.js'])
@endpush