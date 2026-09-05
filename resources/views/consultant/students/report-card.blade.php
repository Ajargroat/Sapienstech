{{--
    Consultant report-card (کارنامه) workspace for one student.

    Rendered by App\Http\Controllers\Consultant\StudentReportCardController::index()
    through the `consultant.student.report-card` route.

    Cards merge company_exam_results (قلم‌چی، ماز، …) with finished consultant-
    made exams from student_assigned_quizzes + tests + student_test_attempts.
    All styling lives in resources/css/app.css (search for "REPORT-CARD
    WORKSPACE"), behavior in resources/js/features/consultant-report-cards.js.
--}}
@extends('layouts.consultant')

@section('content')
@php
    // Company vs built-in badges: same geometry, same 2px stroke language as
    // the exam-type badges; the company one is tinted by --rc-brand.
    $sourceSvg = [
        'company' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="12" y="10" width="24" height="30" rx="5" fill="currentColor" opacity=".12"/>
            <rect x="12" y="10" width="24" height="30" rx="5" stroke="currentColor" stroke-width="2"/>
            <path d="M18 18h4M26 18h4M18 25h4M26 25h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M21 40v-7h6v7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M24 4v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>',
        'internal' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="9" y="8" width="24" height="32" rx="5" fill="currentColor" opacity=".12"/>
            <rect x="9" y="8" width="24" height="32" rx="5" stroke="currentColor" stroke-width="2"/>
            <path d="M15 17h12M15 24h12M15 31h7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M31.5 38.5l8.5-8.5-4-4-8.5 8.5-1.5 5.5 5.5-1.5z" fill="currentColor"/>
        </svg>',
    ];
@endphp
<div id="report-card-app">
    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>کارنامه‌ها</h2>
            <span class="count-badge">{{ persian_digits($total) }} کارنامه</span>
        </div>

        <div class="panel-heading-actions exam-toolbar">
            <div class="search-reveal @if($search !== '') is-open @endif">
                <form method="GET" action="{{ route('consultant.student.report-card', $student) }}" class="search-reveal-form">
                    <button type="submit" class="search-reveal-toggle" aria-label="جستجو">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجوی آزمون یا شرکت…" class="search-reveal-input" aria-label="جستجوی کارنامه">
                    @if($source !== '')<input type="hidden" name="source" value="{{ $source }}">@endif
                    @if($search !== '')
                        <a href="{{ route('consultant.student.report-card', $student) }}" class="search-reveal-clear" aria-label="پاک کردن جستجو">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>

            <div class="filter-wrap">
                <input type="checkbox" id="filter-toggle" class="filter-toggle-input" @checked($source !== '')>
                <label for="filter-toggle" class="filter-toggle-btn" aria-label="فیلتر منبع کارنامه">
                    <i class="fas fa-sliders-h"></i>
                    @if($source !== '')<span class="filter-count">{{ persian_digits($counts[$source] ?? 0) }}</span>@endif
                </label>
                <div class="filter-popover">
                    <p class="filter-popover-title">منبع کارنامه</p>
                    <a href="{{ route('consultant.student.report-card', ['student' => $student, 'search' => $search !== '' ? $search : null]) }}"
                       class="exam-filter-option @if($source === '') is-active @endif">
                        همه <span>{{ persian_digits($total) }}</span>
                    </a>
                    @foreach($sources as $key => $label)
                        <a href="{{ route('consultant.student.report-card', ['student' => $student, 'source' => $key, 'search' => $search !== '' ? $search : null]) }}"
                           class="exam-filter-option @if($source === $key) is-active @endif">
                            {{ $label }} <span>{{ persian_digits($counts[$key] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <button type="button" id="rc-view-toggle" class="exam-tool" data-view="grid"
                    aria-label="تغییر بین نمای کارتی و فهرستی" title="نمایش فهرستی">
                <span class="exam-tool-stack">
                    <svg class="exam-tool-icon icon-grid" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3.5" y="3.5" width="7" height="7" rx="2.5"/>
                        <rect x="13.5" y="3.5" width="7" height="7" rx="2.5"/>
                        <rect x="3.5" y="13.5" width="7" height="7" rx="2.5"/>
                        <rect x="13.5" y="13.5" width="7" height="7" rx="2.5"/>
                    </svg>
                    <svg class="exam-tool-icon icon-list" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6h11M9 12h11M9 18h11"/>
                        <circle cx="4.6" cy="6" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="4.6" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="4.6" cy="18" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
            </button>

            <button type="button" id="open-send-modal" class="exam-tool" aria-label="ارسال کارنامه به اولیا" title="ارسال کارنامه به اولیا">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    @if($cards->isNotEmpty())
        <div class="exam-grid rc-grid" id="rc-grid" data-stagger>
            @foreach($cards as $card)
                <article class="exam-card rc-card" style="--rc-brand: {{ $card['brand'] ?: 'var(--c-primary)' }}">
                    <div class="exam-card-head">
                        <span class="rc-source">
                            {!! $sourceSvg[$card['kind']] !!}
                            <span class="exam-type-label">{{ $card['source_label'] }}</span>
                        </span>
                        <span class="exam-status exam-status--{{ $card['status'] }}">{{ $statuses[$card['status']] ?? $card['status'] }}</span>
                    </div>
                    <div class="exam-card-main">
                        <h3 class="exam-card-title">{{ $card['title'] }}</h3>
                        <p class="exam-card-desc">{{ $card['description'] }}</p>
                        <ul class="exam-card-meta">
                            <li><i class="fas fa-book-open"></i>{{ $card['lesson'] }}</li>
                            @if($card['questions'])
                                <li><i class="fas fa-question-circle"></i>{{ persian_digits($card['questions']) }} سوال</li>
                            @endif
                            @if($card['participants'])
                                <li><i class="fas fa-users"></i>{{ persian_digits(number_format($card['participants'])) }} شرکت‌کننده</li>
                            @endif
                        </ul>
                        @if($card['percent'] !== null)
                            <div class="rc-percent">
                                <div class="rc-progress"><span style="width: {{ $card['percent'] }}%"></span></div>
                                <strong>{{ persian_digits($card['percent']) }}٪</strong>
                            </div>
                        @endif
                    </div>
                    <div class="exam-card-foot">
                        @if($card['date_iso'])
                            <span>
                                <i class="fas fa-calendar-day"></i>
                                <time class="fa-date" datetime="{{ $card['date_iso'] }}">{{ $card['date_text'] }}</time>
                            </span>
                        @endif
                        @if($card['rank'] !== null)
                            <span class="rc-rank" title="رتبه در بین شرکت‌کنندگان">
                                <i class="fas fa-trophy"></i>رتبه {{ persian_digits(number_format($card['rank'])) }}
                            </span>
                        @endif
                        @if($card['score'] !== null)
                            <span class="exam-card-score">{{ persian_digits($card['score']) }} <small>از {{ persian_digits($card['total']) }}</small></span>
                        @endif
                        @if($card['result_url'])
                            <a href="{{ $card['result_url'] }}" class="exam-run-btn" title="مشاهده تحلیل آزمون">
                                <i class="fas fa-chart-bar"></i> تحلیل
                            </a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            @if($search !== '' || $source !== '')
                <h3>کارنامه‌ای با این مشخصات پیدا نشد</h3>
                <p>عبارت جستجو یا فیلتر منبع را تغییر دهید.</p>
            @else
                <h3>هنوز کارنامه‌ای برای این دانش‌آموز ثبت نشده است</h3>
                <p>نتایج آزمون‌های شرکتی و آزمون‌های برگزارشدهٔ مشاور اینجا نمایش داده می‌شوند.</p>
            @endif
        </div>
    @endif

    <dialog id="send-report-card-modal" class="exam-modal">
        <form>
            <div class="exam-modal-head">
                <h3><i class="fas fa-paper-plane"></i> ارسال کارنامه به اولیا</h3>
                <p>نمونه اولیه — ارسال واقعی پس از راه‌اندازی مدل ارسال کارنامه فعال می‌شود.</p>
            </div>
            <div class="exam-modal-body">
                <div class="exam-field">
                    <label for="rc-recipient">گیرنده</label>
                    <select id="rc-recipient" name="recipient">
                        <option>مادر</option>
                        <option>پدر</option>
                        <option>پدر و مادر</option>
                    </select>
                </div>
                <div class="exam-field">
                    <label for="rc-range">محدودهٔ کارنامه‌ها</label>
                    <select id="rc-range" name="range">
                        <option>همهٔ کارنامه‌ها</option>
                        <option>فقط آزمون‌های شرکتی</option>
                        <option>فقط آزمون‌های درون‌ساز</option>
                    </select>
                </div>
                <div class="exam-field exam-field--wide">
                    <label for="rc-note">یادداشت مشاور</label>
                    <textarea id="rc-note" name="note" rows="4" placeholder="مثلاً: روند درصد زیست رو به رشد است اما ریاضی نیاز به برنامه جبرانی دارد…"></textarea>
                </div>
            </div>
            <div class="exam-modal-foot">
                <button type="button" class="secondary-button rc-send-close">بستن</button>
                <button type="submit" class="primary-button" disabled>ارسال کارنامه</button>
            </div>
        </form>
    </dialog>
</div>
@endsection

@vite(['resources/js/features/consultant-report-cards.js'])
