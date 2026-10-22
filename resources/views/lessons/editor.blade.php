{{--
    The lesson block editor: the "contractor" layer on top of the AI draft.

    The left pane is the real book page (so a typo can be checked against the
    source at a glance); the middle is the ordered block list; the right is the
    cropped figure shelf. The whole ordered list posts at once — the array order
    IS the order, and the server renumbers it in one pass.

    Every field is keyed `blocks[INDEX][...]` so a 422 can be pointed at the
    exact input (PREFERENCE §4.1). No colour is hardcoded: the classes live in
    resources/css/features/lessons.css and take their values from tenant tokens.
--}}
@extends('lessons.layout')

@section('lesson-title', $lesson->lesson_title)

@section('lesson-content')
@include('consultant.blog.flash')

<div class="lesson-editor-head">
    <div class="lesson-editor-facts">
        <span class="lesson-status lesson-status--{{ $lesson->status }}">{{ $statusLabels[$lesson->status] ?? $lesson->status }}</span>
        <span class="lesson-chip"><i class="fas fa-book"></i> {{ $lesson->book_title }}</span>
        @if($lesson->chapter_title)
            <span class="lesson-chip"><i class="fas fa-folder-tree"></i> {{ $lesson->chapter_title }}</span>
        @endif
        <span class="lesson-chip">
            <i class="fas fa-file-lines"></i>
            صفحهٔ {{ persian_digits($lesson->source_page_from) }} تا {{ persian_digits($lesson->source_page_to) }}
        </span>
        <span class="lesson-chip"><i class="fas fa-layer-group"></i> {{ persian_digits($blocks->count()) }} بلوک</span>
    </div>

    <div class="lesson-editor-actions">
        @if($aiBlocksEnabled)
            <form method="POST" action="{{ lessons_route('ai.blocks', $lesson) }}">
                @csrf
                <button type="submit" class="secondary-button" title="پیش‌نویس بلوک از متن کتاب، در صف تولید می‌شود">
                    <i class="fas fa-wand-magic-sparkles"></i> پیش‌نویس هوش مصنوعی
                </button>
            </form>
        @endif

        <a href="{{ lessons_route('questions.review', $lesson) }}" class="secondary-button">
            <i class="fas fa-circle-question"></i> سؤال‌ها
        </a>

        @if($lesson->status === 'published')
            <form method="POST" action="{{ lessons_route('editor.revise', $lesson) }}">
                @csrf
                <button type="submit" class="secondary-button"><i class="fas fa-rotate-left"></i> برگشت به پیش‌نویس</button>
            </form>
        @else
            <form method="POST" action="{{ lessons_route('editor.publish', $lesson) }}">
                @csrf
                <button type="submit" class="primary-button"><i class="fas fa-circle-check"></i> انتشار درس</button>
            </form>
        @endif
    </div>
</div>

@if($errors->any())
    <div class="lesson-banner lesson-banner--error">
        <i class="fas fa-triangle-exclamation"></i>
        تغییرات ثبت نشد:
        <ul class="lesson-error-list">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($lastBlockDraft)
    <div class="lesson-banner lesson-banner--info" data-lesson-draft-url="{{ lessons_route('ai.draft', [$lesson, $lastBlockDraft]) }}">
        <i class="fas fa-robot"></i>
        آخرین پیش‌نویس هوش مصنوعی: {{ $draftLabels[$lastBlockDraft->status] ?? $lastBlockDraft->status }}
        @if($lastBlockDraft->error_text)
            — {{ $lastBlockDraft->error_text }}
        @endif
    </div>
@endif

<div class="lesson-editor-grid" data-react-island="consultant-lesson-editor" data-lesson-editor-island>
    {{--
        One server-serialised props payload for the React island. It lives
        inside the host on purpose: `init(region)` reads it before createRoot
        clears the server-rendered fallback below, and a re-render after a page
        router swap gets a fresh copy with the fresh HTML.
    --}}
    <script type="application/json" data-lesson-editor-payload>@json($editorPayload)</script>
    {{-- ---------- source page ---------- --}}
    <aside class="panel student-panel lesson-source-pane">
        <header class="student-panel-head"><h2><i class="fas fa-image"></i> صفحهٔ کتاب</h2></header>

        @php
            $pagesInRange = $pages->filter(fn ($p) => $p->page_number >= ($lesson->source_page_from ?: 0)
                && $p->page_number <= ($lesson->source_page_to ?: PHP_INT_MAX))->values();
        @endphp

        @if($pagesInRange->isEmpty())
            <p class="lesson-empty-note">تصویری برای این صفحه‌ها ذخیره نشده است.</p>
        @else
            <div class="lesson-source-pages" data-lesson-pages>
                @foreach($pagesInRange as $index => $sourcePage)
                    <figure class="lesson-source-page" data-page-number="{{ $sourcePage->page_number }}" @if($index > 0) hidden @endif>
                        <img src="{{ lessons_route('import.page', [$lesson->import, $sourcePage->page_number]) }}"
                             alt="صفحهٔ {{ persian_digits($sourcePage->page_number) }}" loading="lazy">
                        <figcaption>صفحهٔ {{ persian_digits($sourcePage->page_number) }}</figcaption>
                    </figure>
                @endforeach
            </div>

            @if($pagesInRange->count() > 1)
                <div class="lesson-source-switch">
                    @foreach($pagesInRange as $index => $sourcePage)
                        <button type="button" class="lesson-chip {{ $index === 0 ? 'is-active' : '' }}"
                                data-lesson-page-jump="{{ $sourcePage->page_number }}">
                            {{ persian_digits($sourcePage->page_number) }}
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    </aside>

    {{-- ---------- blocks ---------- --}}
    <section class="panel student-panel lesson-blocks-pane">
        <header class="student-panel-head">
            <h2><i class="fas fa-layer-group"></i> بلوک‌های درس</h2>
            <span class="count-badge">{{ persian_digits($blocks->count()) }}</span>
        </header>

        <form method="POST" action="{{ lessons_route('editor.blocks', $lesson) }}" id="lesson-blocks-form">
            @csrf

            <div class="lesson-blocks" data-lesson-blocks>
                @forelse($blocks as $index => $block)
                    @include('lessons.partials.block-row', ['index' => $index, 'block' => $block, 'blockTypeLabels' => $blockTypeLabels])
                @empty
                    <p class="lesson-empty-note" data-lesson-blocks-empty>
                        این درس هنوز بلوکی ندارد. با «پیش‌نویس هوش مصنوعی» شروع کنید یا یک بلوک دستی اضافه کنید.
                    </p>
                @endforelse
            </div>

            <div class="lesson-add-row">
                <label class="settings-field">
                    <span class="settings-field-label">افزودن بلوک</span>
                    <select class="settings-input" data-lesson-add-type>
                        @foreach($blockTypeLabels as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="secondary-button" data-lesson-add-block>
                    <i class="fas fa-plus"></i> افزودن
                </button>
            </div>

            <div class="teacher-form-actions">
                <button type="submit" class="primary-button"><i class="fas fa-floppy-disk"></i> ثبت تغییرات</button>
            </div>
        </form>
    </section>

    {{-- ---------- figure shelf ---------- --}}
    <aside class="panel student-panel lesson-figure-pane">
        <header class="student-panel-head"><h2><i class="fas fa-crop-simple"></i> شکل‌های برش‌خورده</h2></header>

        @if($figures->isEmpty())
            <p class="lesson-empty-note">شکلی در این محدودهٔ صفحه پیدا نشد.</p>
        @else
            <div class="lesson-figure-shelf">
                @foreach($figures as $figure)
                    <button type="button" class="lesson-figure-pick lesson-figure-card--{{ $figure->confidence }}"
                            data-lesson-figure-id="{{ $figure->id }}"
                            data-lesson-figure-url="{{ \App\Support\TenantUploads::url($figure->image_path) }}"
                            data-lesson-figure-page="{{ $figure->page_number }}"
                            title="صفحهٔ {{ persian_digits($figure->page_number) }} — برای افزودن به انتهای درس کلیک کنید">
                        <img src="{{ \App\Support\TenantUploads::url($figure->image_path) }}"
                             alt="شکل صفحهٔ {{ persian_digits($figure->page_number) }}" loading="lazy">
                    </button>
                @endforeach
            </div>
            <p class="lesson-empty-note">روی یک شکل بزنید تا به انتهای درس اضافه شود.</p>
        @endif
    </aside>
</div>

@include('lessons.partials.block-templates', ['blockTypeLabels' => $blockTypeLabels])
@endsection

@push('lesson-scripts')
    @vite(['resources/js/features/consultant-lesson-editor.js'])
@endpush
