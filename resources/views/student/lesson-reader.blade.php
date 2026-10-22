{{--
    The student lesson reader — the screen the feature exists for.

    Structure mirrors the approved demo 1:1: breadcrumb, title + lead, meta
    chips, sticky sidebar table of contents with scroll-spy and a reading
    meter, then the article itself with floats, callouts and equation cards.
    Every block renders in flow order; floats are CSS, not a second layout.

    Colour comes from tenant tokens (resources/css/features/lessons.css), and
    no font is loaded from a CDN — only the tenant's own /fonts (PREFERENCE §2).
--}}
@extends('layouts.student')

@php
    use App\Support\Lessons\LessonBlocks;

    $figureUrl = fn ($figure) => $figure
        ? \App\Support\TenantUploads::url($figure->image_path)
        : null;

    // Named keys, not a positional list: the route's parameter is
    // `{pageNumber}` because `page` is reserved by the paginator, and Laravel
    // fills positional parameters in declaration order — which silently
    // mismatches the moment a second parameter's name changes.
    $pageUrl = fn (int $number) => route('student.lesson-reader.page', [
        'lesson' => $lesson,
        'pageNumber' => $number,
    ]);

    $placementClass = function (array $data): string {
        return match ($data['placement'] ?? 'wide') {
            'right' => 'lesson-float lesson-float--start',
            'left' => 'lesson-float lesson-float--end',
            default => 'lesson-float lesson-float--wide',
        };
    };
@endphp

@section('content')
<article class="lesson-reader" data-lesson-reader
         data-csrf="{{ csrf_token() }}"
         data-progress-url="{{ route('student.lesson-reader.progress', $lesson) }}"
         data-answer-url="{{ route('student.lesson-reader.answer', $lesson) }}"
         data-progress-index="{{ $progressIndex }}">

    <header class="lesson-reader-head">
        <nav class="lesson-breadcrumb" aria-label="مسیر">
            <a href="{{ route('student.dashboard') }}">داشبورد</a>
            <span aria-hidden="true">/</span>
            <span>{{ $lesson->book_title }}</span>
            @if($lesson->chapter_title)
                <span aria-hidden="true">/</span>
                <span>{{ $lesson->chapter_title }}</span>
            @endif
        </nav>

        <h1 class="lesson-reader-title">{{ $lesson->lesson_title }}</h1>

        @if($lesson->chapter_title)
            <p class="lesson-reader-lead">{{ $lesson->chapter_title }} — {{ $lesson->book_title }}</p>
        @endif

        <div class="lesson-reader-chips">
            <span class="lesson-chip">
                <i class="fas fa-book"></i> {{ $lesson->book_title }}
            </span>
            <span class="lesson-chip">
                <i class="fas fa-file-lines"></i>
                صفحه‌های {{ persian_digits($lesson->source_page_from) }}–{{ persian_digits($lesson->source_page_to) }}
            </span>
            @if($lesson->grade)
                <span class="lesson-chip"><i class="fas fa-graduation-cap"></i> پایهٔ {{ $lesson->grade }}</span>
            @endif

            {{-- The demo's «۳ پرسش» chip: a reader should know a quiz is
                 waiting at the end before they get there. --}}
            @if($questions->isNotEmpty())
                <span class="lesson-chip">
                    <i class="fas fa-circle-question"></i> {{ persian_digits($questions->count()) }} پرسش
                </span>
            @endif
            @if($lesson->published_at)
                <span class="lesson-chip">
                    <i class="far fa-calendar"></i>
                    <time class="fa-date" datetime="{{ $lesson->published_at->toIso8601String() }}Z">
                        {{ persian_digits($lesson->published_at->format('Y/m/d')) }}
                    </time>
                </span>
            @endif

            <a class="lesson-chip" href="{{ $pageUrl((int) $lesson->source_page_from) }}"
               target="_blank" rel="noopener" title="صفحهٔ اصلی کتاب را باز می‌کند">
                <i class="fas fa-image"></i> صفحهٔ کتاب
            </a>
        </div>
    </header>

    <div class="lesson-reader-body">
        {{-- ---------- sticky TOC + reading meter ---------- --}}
        <aside class="lesson-toc" aria-label="فهرست درس">
            <div class="lesson-meter" aria-hidden="true">
                <div class="lesson-meter-bar" data-lesson-meter style="inline-size: 0%"></div>
            </div>

            <span class="lesson-toc-title"><i class="fas fa-list"></i> در این درس</span>

            <ol class="lesson-toc-list">
                @foreach($sections as $section)
                    <li>
                        <a href="#{{ $section['anchor'] }}" data-lesson-toc="{{ $section['index'] }}">
                            {{ $section['text'] !== '' ? $section['text'] : 'بخش '.persian_digits($section['index'] + 1) }}
                        </a>
                    </li>
                @endforeach

                @if($questions->isNotEmpty())
                    {{-- Not a section: the quiz has no heading block, so it must
                         not shift the scroll-spy indices the meter counts in. --}}
                    <li>
                        <a href="#lesson-quiz" class="lesson-toc-quiz">پرسش‌ها</a>
                    </li>
                @endif
            </ol>

            <span class="lesson-meter-label" data-lesson-meter-label>۰٪ خوانده‌اید</span>
        </aside>

        {{-- ---------- the lesson ---------- --}}
        <div class="lesson-prose">
            @php $headingIndex = -1; @endphp

            @forelse($blocks as $block)
                @php $data = (array) ($block->data ?? []); @endphp

                @switch($block->type)
                    @case('heading')
                        @php $headingIndex++; @endphp
                        @if((int) ($data['level'] ?? 2) === 3)
                            <h3 id="{{ $sections[$headingIndex]['anchor'] ?? 'section-'.$headingIndex }}"
                                class="lesson-h3 lesson-anchor"
                                data-lesson-section="{{ $sections[$headingIndex]['index'] ?? $headingIndex }}">
                                {{ $data['text'] ?? '' }}
                            </h3>
                        @else
                            <h2 id="{{ $sections[$headingIndex]['anchor'] ?? 'section-'.$headingIndex }}"
                                class="lesson-h2 lesson-anchor"
                                data-lesson-section="{{ $sections[$headingIndex]['index'] ?? $headingIndex }}">
                                {{ $data['text'] ?? '' }}
                            </h2>
                        @endif
                        @break

                    @case('paragraph')
                        <p class="lesson-p">{{ $data['text'] ?? '' }}</p>
                        @break

                    @case('points')
                        <ul class="lesson-pts">
                            @foreach((array) ($data['items'] ?? []) as $item)
                                <li>
                                    @if(! empty($item['icon']))<span class="lesson-pts-icon" aria-hidden="true">{{ $item['icon'] }}</span>@endif
                                    @if(! empty($item['lead']))<strong>{{ $item['lead'] }}</strong>@endif
                                    <span>{{ $item['text'] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @break

                    @case('equation')
                        <div class="lesson-eq" dir="ltr">
                            <span class="lesson-eq-expr">{{ $data['expr'] ?? '' }}</span>
                            @if(! empty($data['caption']))
                                <span class="lesson-eq-caption" dir="rtl">{{ $data['caption'] }}</span>
                            @endif
                        </div>
                        @break

                    @case('tip')
                        <aside class="lesson-tip">
                            <span class="lesson-tip-title">
                                <i class="fas fa-lightbulb"></i>
                                {{ $data['title'] ?? 'نکته' }}
                            </span>
                            <p>{{ $data['text'] ?? '' }}</p>
                        </aside>
                        @break

                    @case('figure')
                        @php $figure = $figures->get((int) ($data['figure_id'] ?? 0)); @endphp
                        @if($figure)
                            <figure class="{{ $placementClass($data) }}">
                                <img src="{{ $figureUrl($figure) }}"
                                     alt="{{ $data['caption'] ?? 'شکل صفحهٔ '.persian_digits($figure->page_number) }}"
                                     loading="lazy">
                                <figcaption>
                                    {{ $data['caption'] ?? 'شکل صفحهٔ '.persian_digits($figure->page_number) }}
                                    <a href="{{ $pageUrl((int) $figure->page_number) }}"
                                       target="_blank" rel="noopener" class="lesson-source-link">
                                        صفحهٔ {{ persian_digits($figure->page_number) }} کتاب
                                    </a>
                                </figcaption>
                            </figure>
                        @endif
                        @break

                    @case('figure_duo')
                        @php
                            $paired = collect((array) ($data['figure_ids'] ?? []))
                                ->map(fn ($id) => $figures->get((int) $id))
                                ->filter();
                        @endphp
                        @if($paired->count() >= 2)
                            <figure class="lesson-float lesson-float--wide lesson-duo">
                                <div class="lesson-duo-row">
                                    @foreach($paired->take(2) as $figure)
                                        <img src="{{ $figureUrl($figure) }}"
                                             alt="شکل صفحهٔ {{ persian_digits($figure->page_number) }}" loading="lazy">
                                    @endforeach
                                </div>
                                @if(! empty($data['caption']))
                                    <figcaption>{{ $data['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endif
                        @break
                @endswitch
            @empty
                <div class="empty-state">
                    <i class="far fa-file"></i>
                    <h3>این درس هنوز محتوایی ندارد</h3>
                    <p>به‌زودی از طرف مشاور شما منتشر می‌شود.</p>
                </div>
            @endforelse

            {{--
                The quiz is the reason the AI half of this feature exists: a
                reviewer releases questions into the bank, and the reader shows
                the ones that belong to THIS lesson. The answer key is never in
                the markup — the demo this came from printed it because the demo
                was a static page; a real student would just read the answers.
            --}}
            @if($questions->isNotEmpty())
                <section class="lesson-quiz" id="lesson-quiz" aria-labelledby="lesson-quiz-title">
                    <h2 class="lesson-h2" id="lesson-quiz-title">خودتان را بسنجید</h2>
                    <p class="lesson-quiz-hint">پاسخ‌ها همان لحظه بررسی می‌شوند.</p>

                    @foreach($questions as $position => $question)
                        <article class="lesson-q" data-lesson-question
                                 data-question="{{ $question->id }}">
                            <div class="lesson-q-head">
                                <span class="lesson-q-tag">چندگزینه‌ای</span>
                                <span class="lesson-q-tag">
                                    پرسش {{ persian_digits($position + 1) }} از {{ persian_digits($questions->count()) }}
                                </span>
                            </div>

                            <h3 class="lesson-q-stem">{{ $question->question_text }}</h3>

                            <fieldset class="lesson-q-opts" aria-label="گزینه‌ها">
                                @foreach($question->answers as $answer)
                                    <label class="lesson-q-opt">
                                        <input type="radio"
                                               name="question-{{ $question->id }}"
                                               value="{{ $answer->id }}">
                                        <span class="lesson-q-dot" aria-hidden="true"></span>
                                        <span class="lesson-q-text">{{ $answer->answer_text }}</span>
                                    </label>
                                @endforeach
                            </fieldset>

                            <div class="lesson-q-actions">
                                <button class="primary-button lesson-q-submit" type="button" data-lesson-q-submit>
                                    ثبت پاسخ
                                </button>
                                <span class="lesson-q-note" data-lesson-q-note></span>
                            </div>

                            <p class="lesson-q-feedback" data-lesson-q-feedback hidden></p>
                        </article>
                    @endforeach
                </section>
            @endif

            <footer class="lesson-reader-foot">
                <a class="primary-button" href="{{ route('student.dashboard') }}">
                    <i class="fas fa-arrow-right"></i> بازگشت به داشبورد
                </a>
                <a class="secondary-button" href="{{ $pageUrl((int) $lesson->source_page_from) }}"
                   target="_blank" rel="noopener">
                    <i class="fas fa-book-open"></i> دیدن صفحهٔ اصلی کتاب
                </a>
            </footer>
        </div>
    </div>
</article>

@vite(['resources/js/features/student-lesson-reader.js'])
@endsection
