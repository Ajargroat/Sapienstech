{{--
    One book import: what the extractor found, the page grid, the figures it
    cropped, and the "build a draft lesson from pages X..Y" form.
--}}
@extends('lessons.layout')

@section('lesson-title', $import->book_title)

@section('lesson-content')
@include('consultant.blog.flash')

@if($running)
    <div class="lesson-banner lesson-banner--info" data-lesson-poll="{{ lessons_route('import.show', $import) }}">
        <i class="fas fa-spinner fa-spin"></i>
        در حال استخراج صفحه‌ها… این صفحه خودکار به‌روز می‌شود.
    </div>
@endif

@if($import->status === 'needs_ocr')
    <div class="lesson-banner lesson-banner--warn">
        <i class="fas fa-triangle-exclamation"></i>
        متن استخراج‌شده کم است؛ این کتاب اسکن‌شده است. تصویر صفحه‌ها و شکل‌ها آماده‌اند،
        اما متن باید دستی نوشته شود (نصب OCR در <code>scripts/pdf/README.md</code> توضیح داده شده است).
    </div>
@endif

@if($import->status === 'failed')
    <div class="lesson-banner lesson-banner--error">
        <i class="fas fa-circle-xmark"></i>
        استخراج ناموفق بود: {{ $import->error_text ?: ($import->error_code ?? '—') }}
    </div>
@endif

<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-circle-info"></i> وضعیت استخراج</h2>
        <span class="lesson-status lesson-status--{{ $import->status }}">{{ $statusLabels[$import->status] ?? $import->status }}</span>
    </header>

    <ul class="exam-card-facts lesson-import-facts">
        <li><i class="fas fa-file-lines"></i>{{ persian_digits($import->page_count ?? $import->sourcePages->count()) }} صفحه</li>
        <li><i class="fas fa-font"></i>{{ $structureLabels[$import->structure ?? 'unknown'] ?? 'نامشخص' }}</li>
        <li><i class="fas fa-image"></i>{{ persian_digits($import->figures->count()) }} شکل برش‌خورده</li>
        <li><i class="fas fa-layer-group"></i>{{ $import->grade ?: 'همه پایه‌ها' }}</li>
    </ul>
</div>

@if($import->status === 'ready')
    <div class="panel student-panel">
        <header class="student-panel-head">
            <h2><i class="fas fa-wand-magic-sparkles"></i> ساخت درس از صفحه‌های کتاب</h2>
        </header>

        <form method="POST" action="{{ lessons_route('import.build', $import) }}" class="teacher-form">
            @csrf
            <div class="teacher-form-grid">
                <label class="settings-field">
                    <span class="settings-field-label">عنوان درس</span>
                    <input type="text" name="lesson_title" value="{{ old('lesson_title', $import->book_title) }}" class="settings-input" required>
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">فصل (اختیاری)</span>
                    <input type="text" name="chapter_title" value="{{ old('chapter_title') }}" class="settings-input">
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">از صفحه</span>
                    <input type="number" min="1" name="page_from" value="{{ old('page_from', $import->sourcePages->min('page_number') ?: 1) }}" class="settings-input" required>
                </label>
                <label class="settings-field">
                    <span class="settings-field-label">تا صفحه</span>
                    <input type="number" min="1" name="page_to" value="{{ old('page_to', $import->sourcePages->min('page_number') ?: 1) }}" class="settings-input" required>
                </label>
            </div>
            <div class="teacher-form-actions">
                <button type="submit" class="primary-button"><i class="fas fa-plus"></i> ساخت پیش‌نویس درس</button>
            </div>
        </form>
    </div>
@endif

@if($import->lessons->isNotEmpty())
    <div class="panel student-panel">
        <header class="student-panel-head"><h2><i class="fas fa-book-open-reader"></i> درس‌های این کتاب</h2></header>
        <div class="lesson-import-list">
            @foreach($import->lessons as $lesson)
                <article class="lesson-import-row">
                    <div class="lesson-import-main">
                        <a class="lesson-import-title" href="{{ lessons_route('editor.edit', $lesson) }}">{{ $lesson->lesson_title }}</a>
                        <ul class="exam-card-facts">
                            <li><i class="fas fa-file-lines"></i> صفحهٔ {{ persian_digits($lesson->source_page_from) }} تا {{ persian_digits($lesson->source_page_to) }}</li>
                        </ul>
                    </div>
                    <span class="lesson-status lesson-status--{{ $lesson->status }}">{{ $lesson->status }}</span>
                </article>
            @endforeach
        </div>
    </div>
@endif

<div class="panel student-panel">
    <header class="student-panel-head"><h2><i class="fas fa-images"></i> صفحه‌ها و شکل‌های برش‌خورده</h2></header>

    <div class="lesson-page-grid">
        @foreach($pages as $pageNumber => $pageRows)
            @foreach($pageRows as $row)
                <figure class="lesson-page-card">
                    <a href="{{ lessons_route('import.page', [$import, $pageNumber]) }}" target="_blank" rel="noopener">
                        <img src="{{ lessons_route('import.page', [$import, $pageNumber]) }}" alt="صفحهٔ {{ persian_digits($pageNumber) }}" loading="lazy">
                    </a>
                    <figcaption>
                        صفحهٔ {{ persian_digits($pageNumber) }}
                        @if($row->char_count > 0)
                            · {{ persian_digits($row->char_count) }} نویسه
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        @endforeach
    </div>

    @if($figures->isNotEmpty())
        <h3 class="teacher-subject-head"><i class="fas fa-crop-simple"></i> شکل‌ها</h3>
        <div class="lesson-figure-strip">
            @foreach($figures as $pageNumber => $pageFigures)
                @foreach($pageFigures as $figure)
                    <figure class="lesson-figure-card lesson-figure-card--{{ $figure->confidence }}">
                        <img src="{{ \App\Support\TenantUploads::url($figure->image_path) }}" alt="شکل صفحهٔ {{ persian_digits($pageNumber) }}" loading="lazy">
                        <figcaption>صفحهٔ {{ persian_digits($pageNumber) }}</figcaption>
                    </figure>
                @endforeach
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('lesson-scripts')
    @vite(['resources/js/features/consultant-lesson-import.js'])
@endpush
