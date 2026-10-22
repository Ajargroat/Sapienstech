{{--
    AI question review: the human gate in front of the shared question bank.

    The draft is the unit of review (its questions were written together, so
    they are read together), but release is per question — a reviewer keeps the
    three good ones and drops the fourth. Rejected drafts stay listed: they are
    the audit trail of what a model proposed and a person refused.

    Every AI question is labelled «پیش‌نویس هوش مصنوعی» until a human approves
    it, and a separate flag guards the write into the shared pool — reviewing is
    harmless, publishing to every tenant is not.
--}}
@extends('lessons.layout')

@section('lesson-title', 'سؤال‌های درس: '.$lesson->lesson_title)

@section('lesson-content')
@include('consultant.blog.flash')

<div class="lesson-editor-head">
    <div class="lesson-editor-facts">
        <a class="lesson-chip" href="{{ lessons_route('editor.edit', $lesson) }}">
            <i class="fas fa-arrow-right"></i> بازگشت به ویرایشگر
        </a>
        <span class="lesson-chip"><i class="fas fa-book"></i> {{ $lesson->book_title }}</span>
        <span class="lesson-chip">
            <i class="fas fa-file-lines"></i>
            صفحهٔ {{ persian_digits($lesson->source_page_from) }} تا {{ persian_digits($lesson->source_page_to) }}
        </span>
    </div>

    @if($aiEnabled)
        <form method="POST" action="{{ lessons_route('ai.questions', $lesson) }}" class="lesson-inline-form">
            @csrf
            <label class="settings-field settings-field--narrow">
                <span class="settings-field-label">تعداد سؤال</span>
                <input type="number" class="settings-input" name="count" min="3" max="{{ $maxQuestions }}"
                       value="{{ $defaultQuestions }}">
            </label>
            <button type="submit" class="primary-button"><i class="fas fa-wand-magic-sparkles"></i> تولید سؤال</button>
        </form>
    @endif
</div>

@if(! $aiEnabled)
    <div class="lesson-banner lesson-banner--warn">
        <i class="fas fa-circle-pause"></i>
        تولید سؤال با هوش مصنوعی برای این مجموعه خاموش است. سؤال‌های موجود در بانک از راه آزمون‌ها ساخته می‌شوند.
    </div>
@endif

@if($pendingDraft)
    <div class="lesson-banner lesson-banner--info" data-lesson-draft-url="{{ lessons_route('ai.draft', [$lesson, $pendingDraft]) }}">
        <i class="fas fa-spinner fa-spin"></i>
        یک پیش‌نویس {{ $draftLabels[$pendingDraft->status] ?? $pendingDraft->status }} است؛ این صفحه خودکار به‌روز می‌شود.
    </div>
@endif

@if($drafts->isEmpty())
    <div class="panel student-panel">
        <div class="empty-state">
            <i class="far fa-circle-question"></i>
            <h3>هنوز پیش‌نویسی ساخته نشده</h3>
            <p>پس از تولید، سؤال‌ها اینجا برای بررسی شما نمایش داده می‌شوند.</p>
        </div>
    </div>
@else
    @foreach($drafts as $draft)
        @php
            $questions = $draft->questions();
            $released = $draft->released_question_id !== null;
        @endphp

        <section class="panel student-panel lesson-draft" data-lesson-draft="{{ $draft->id }}">
            <header class="student-panel-head">
                <h2>
                    <i class="fas fa-robot"></i>
                    پیش‌نویس هوش مصنوعی
                    <span class="lesson-status lesson-status--{{ $draft->status }}">{{ $draftLabels[$draft->status] ?? $draft->status }}</span>
                </h2>
                <span class="lesson-chip">
                    {{ persian_digits(count($questions)) }} سؤال
                    @if($draft->model)
                        · {{ $draft->model }}
                    @endif
                </span>
            </header>

            @if($draft->error_text)
                <div class="lesson-banner lesson-banner--error">
                    <i class="fas fa-circle-xmark"></i> {{ $draft->error_text }}
                </div>
            @endif

            @if($questions === [])
                <p class="lesson-empty-note">این پیش‌نویس سؤالی ندارد.</p>
            @else
                <ol class="lesson-question-list">
                    @foreach($questions as $questionIndex => $question)
                        <li class="lesson-question">
                            <p class="lesson-question-stem">{{ $question['stem'] ?? '' }}</p>

                            <ul class="lesson-question-options">
                                @foreach((array) ($question['options'] ?? []) as $option)
                                    <li class="{{ ! empty($option['is_correct']) ? 'is-correct' : '' }}">
                                        <i class="fas {{ ! empty($option['is_correct']) ? 'fa-circle-check' : 'fa-circle' }}"></i>
                                        {{ $option['text'] ?? '' }}
                                    </li>
                                @endforeach
                            </ul>

                            @if(! empty($question['difficulty']))
                                <span class="lesson-chip">{{ $question['difficulty'] }}</span>
                            @endif

                            @if(! empty($question['source_quote']))
                                <blockquote class="lesson-quote">{{ $question['source_quote'] }}</blockquote>
                            @endif

                            @if(! empty($question['explanation']))
                                <p class="lesson-question-why"><strong>توضیح:</strong> {{ $question['explanation'] }}</p>
                            @endif

                            @if($draft->status === 'approved' && $releaseEnabled && ! $released)
                                <form method="POST" action="{{ lessons_route('questions.release', [$lesson, $draft]) }}">
                                    @csrf
                                    <input type="hidden" name="index" value="{{ $questionIndex }}">
                                    <button type="submit" class="secondary-button">
                                        <i class="fas fa-database"></i> افزودن به بانک سؤال
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            <div class="lesson-draft-actions">
                @if($draft->status === 'ready')
                    <form method="POST" action="{{ lessons_route('questions.approve', [$lesson, $draft]) }}">
                        @csrf
                        <button type="submit" class="primary-button"><i class="fas fa-check"></i> تأیید پیش‌نویس</button>
                    </form>
                @endif

                @if(in_array($draft->status, ['ready', 'approved'], true))
                    <form method="POST" action="{{ lessons_route('questions.reject', [$lesson, $draft]) }}">
                        @csrf
                        <button type="submit" class="danger-button"><i class="fas fa-ban"></i> رد کردن</button>
                    </form>
                @endif

                @if($draft->status === 'approved' && $releaseEnabled && ! $released && count($questions) > 1)
                    <form method="POST" action="{{ lessons_route('questions.release-all', [$lesson, $draft]) }}">
                        @csrf
                        <button type="submit" class="secondary-button"><i class="fas fa-layer-group"></i> افزودن همه به بانک</button>
                    </form>
                @endif

                @if($released)
                    <span class="lesson-chip lesson-chip--dirty"><i class="fas fa-database"></i> وارد بانک سؤال شد</span>
                @endif

                @if(! $releaseEnabled && $draft->status === 'approved')
                    <span class="lesson-empty-note">افزودن به بانک سؤال برای این مجموعه خاموش است.</span>
                @endif
            </div>
        </section>
    @endforeach
@endif
@endsection

@push('lesson-scripts')
    @vite(['resources/js/features/consultant-lesson-import.js'])
@endpush
