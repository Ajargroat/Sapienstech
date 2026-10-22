@extends('layouts.ops')

@section('title', 'مدیریت درس')

@section('content')
    {{--
        The page that answers "how does a student see this?".

        `build` produced a DRAFT, drafts are invisible to students, and the only
        publish button lived in the tenant editor — so the console dead-ended
        and the AI questions never reached a lesson page. Everything needed to
        finish the chain is on this screen, in the order a student meets it:
        publish (for a grade), write the questions, release them.
    --}}
    <section class="ops-card">
        <h2>{{ $lesson->lesson_title }}</h2>

        <div class="ops-meta">
            <div><b>کتاب:</b> {{ $lesson->book_title }}</div>
            @if($lesson->chapter_title)
                <div><b>فصل:</b> {{ $lesson->chapter_title }}</div>
            @endif
            <div><b>پایه:</b> {{ $lesson->grade ?: 'همهٔ پایه‌ها' }}</div>
            <div>
                <b>صفحه‌ها:</b>
                {{ persian_digits((int) $lesson->source_page_from) }}–{{ persian_digits((int) $lesson->source_page_to) }}
            </div>
            <div><b>وضعیت:</b> {{ $lessonLabels[$lesson->status] ?? $lesson->status }}</div>
            <div><b>بلوک‌ها:</b> {{ persian_digits($blocks->count()) }}</div>
        </div>
    </section>

    <section class="ops-card">
        <h2>دانش‌آموز چه چیزی می‌بیند؟</h2>

        @if($lesson->status === 'published')
            <p class="ops-hint">
                این درس در
                <a href="{{ route('student.lessons.index') }}" target="_blank" rel="noopener">کتابخانهٔ درس‌های دانش‌آموز</a>
                دیده می‌شود
                @if($lesson->grade)
                    — فقط برای پایهٔ <b>{{ $lesson->grade }}</b>.
                @else
                    — برای <b>همهٔ پایه‌ها</b>.
                @endif
                می‌توانید همین حالا
                <a href="{{ route('student.lesson-reader.show', $lesson) }}" target="_blank" rel="noopener">صفحهٔ درس</a>
                را هم ببینید.
            </p>

            <form class="ops-inline-form" method="POST" action="{{ route('ops.lessons.unpublish', $lesson) }}">
                @csrf
                <button class="ops-button ops-button--ghost ops-button--sm" type="submit">لغو انتشار (بازگشت به پیش‌نویس)</button>
            </form>
        @else
            <p class="ops-hint">
                این درس <b>پیش‌نویس</b> است و هیچ دانش‌آموزی آن را نمی‌بیند. تا وقتی منتشر نشود،
                زحمت استخراج و پرسش‌ها به دانش‌آموز نمی‌رسد. خودتان می‌توانید
                <a href="{{ route('student.lesson-reader.show', $lesson) }}" target="_blank" rel="noopener">پیش‌نمایش درس</a>
                را ببینید (دانش‌آموزان نمی‌توانند).
            </p>
        @endif

        <form method="POST" action="{{ route('ops.lessons.publish', $lesson) }}">
            @csrf

            <div class="ops-grid">
                <label class="ops-field">
                    <span>عنوان درس</span>
                    <input class="ops-input" type="text" name="lesson_title"
                           value="{{ old('lesson_title', $lesson->lesson_title) }}" required>
                </label>

                <label class="ops-field">
                    <span>پایه (خالی = همهٔ پایه‌ها)</span>
                    <input class="ops-input" type="text" name="grade"
                           value="{{ old('grade', $lesson->grade) }}" placeholder="یازدهم">
                </label>
            </div>

            <div class="ops-actions ops-actions--spaced">
                <button class="ops-button" type="submit">
                    {{ $lesson->status === 'published' ? 'به‌روزرسانی انتشار' : 'انتشار برای دانش‌آموزان' }}
                </button>
            </div>
        </form>
    </section>

    <section class="ops-card">
        <h2>پرسش‌های پایان درس</h2>

        @unless($aiEnabled)
            <p class="ops-hint">
                تولید پرسش با AI در تنظیمات این سایت خاموش است
                (<code>lessons.ai_questions_enabled</code>).
            </p>
        @else
            <p class="ops-hint">
                تولید پرسش همین‌جا و در همین درخواست انجام می‌شود؛ کارگر صف لازم نیست.
                پس از آماده شدن، «تأیید و افزودن به درس» پرسش‌ها را به انتهای صفحهٔ درس می‌آورد
                (و هم‌زمان به بانک مشترک پرسش‌ها هم اضافه می‌کند).
            </p>

            <form class="ops-inline-form" method="POST" action="{{ route('ops.lessons.ask', $lesson) }}">
                @csrf
                <label class="ops-field">
                    <span>تعداد پرسش</span>
                    <input class="ops-input" type="number" name="count" min="3" max="{{ $maxQuestions }}"
                           value="{{ $defaultQuestions }}">
                </label>
                <button class="ops-button" type="submit">تولید پرسش با AI</button>
            </form>
        @endunless

        @if($drafts->isNotEmpty())
            <table class="ops-table ops-table--spaced">
                <thead>
                <tr>
                    <th>پیش‌نویس</th>
                    <th>وضعیت</th>
                    <th>پرسش‌ها</th>
                    <th>منتشرشده در درس</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($drafts as $draft)
                    @php($total = count($draft->questions()))
                    <tr>
                        <td>{{ persian_digits((int) $draft->id) }}</td>
                        <td>
                            <span class="ops-status {{ $draft->status === 'failed' || $draft->status === 'refused' ? 'ops-status--bad' : ($draft->status === 'released' ? 'ops-status--ok' : '') }}">
                                {{ $draftLabels[$draft->status] ?? $draft->status }}
                            </span>
                            @if($draft->error_text)
                                <div class="ops-hint">{{ $draft->error_text }}</div>
                            @endif
                        </td>
                        <td>{{ persian_digits($total) }}</td>
                        <td>{{ persian_digits((int) $draft->released_count) }} از {{ persian_digits($total) }}</td>
                        <td>
                            @if(in_array($draft->status, ['ready', 'approved'], true) && $releaseEnabled)
                                <form class="ops-inline-form" method="POST" action="{{ route('ops.lessons.release', $lesson) }}">
                                    @csrf
                                    <input type="hidden" name="draft" value="{{ $draft->id }}">
                                    <button class="ops-button ops-button--sm" type="submit">تأیید و افزودن به درس</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="ops-empty">هنوز پیش‌نویسی برای این درس ساخته نشده است.</p>
        @endif

        @if($questions->isNotEmpty())
            <table class="ops-table ops-table--spaced">
                <thead>
                <tr>
                    <th>پرسش</th>
                    <th>گزینه‌ها</th>
                    <th>پاسخ درست</th>
                </tr>
                </thead>
                <tbody>
                @foreach($questions as $question)
                    <tr>
                        <td>{{ $question->question_text }}</td>
                        <td>{{ persian_digits($question->answers->count()) }}</td>
                        <td>{{ $question->answers->firstWhere('is_correct', true)?->answer_text ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <p class="ops-hint">
                پاسخ درست در صفحهٔ دانش‌آموز چاپ نمی‌شود؛ فقط پس از ثبت پاسخ نشان داده می‌شود.
            </p>
        @endif
    </section>

    <section class="ops-card">
        <h2>متن درس</h2>

        @if($blocks->isEmpty())
            <p class="ops-empty">این درس هیچ بلوکی ندارد؛ ساخت درس را دوباره اجرا کنید.</p>
        @else
            <table class="ops-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>نوع</th>
                    <th>متن</th>
                    <th>صفحه</th>
                </tr>
                </thead>
                <tbody>
                @foreach($blocks->take(30) as $block)
                    <tr>
                        <td>{{ persian_digits((int) $block->position) }}</td>
                        <td>{{ $block->type }}</td>
                        <td>{{ \Illuminate\Support\Str::limit((string) ($block->data['text'] ?? $block->data['expression'] ?? ''), 90) }}</td>
                        <td>{{ $block->source_page ? persian_digits((int) $block->source_page) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
