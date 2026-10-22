{{--
    Student report card (کارنامه) — the student's own page.

    Rendered by App\Http\Controllers\Student\StudentReportCardController::index()
    through `student.report-card`. Everything on it comes from ONE contract,
    App\Support\ReportCard::forStudent(), the same array the dashboard's
    تحلیل strip reads — so the two surfaces cannot disagree.

    Layout (top to bottom):
      1. hero       — the newest score as an arc + the summary facts
      2. trend      — every scored exam, oldest → newest, on one baseline
      3. subjects   — newest value per subject, its direction and its distance
                      from the pass line (کارنامه's own pass line from Settings)
      4. needs-work — subjects whose NEWEST value sits under the pass line
      5. absences   — finished exams nobody sat (progress includes these)
      6. log        — the full row list, newest first

    The colours are tenant tokens only (var(--c-*) + color-mix), so this page
    re-themes with the tenant like every other student surface. The scroll
    reveal is resources/js/features/student-report-card.js.
--}}
@extends('layouts.student')

@section('content')
@php
    $summary  = $report['summary'];
    $passLine = $report['pass_line'];
    // '' for a missing value, so "no number yet" never prints «۰».
    $fa       = fn ($value) => $value === null
        ? ''
        : persian_digits(rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.'));
    // Trend geometry: one chart, every scored exam on the same 0..100 axis.
    $history = $report['history'];
    $points  = [];
    foreach (array_reverse($history) as $i => $row) {
        $points[] = [
            'x' => count($history) > 1 ? round($i / (count($history) - 1) * 100, 2) : 50,
            'y' => round(100 - (float) $row['percent'], 2),
        ];
    }
    $polyline = implode(' ', array_map(fn ($p) => $p['x'].','.$p['y'], $points));
    $trendTone = $summary['delta_direction'];
@endphp
<div class="page-surface rc-page">
    <header class="panel-heading">
        <div class="panel-heading-title">
            <h2><i class="fas fa-chart-pie" aria-hidden="true"></i> کارنامهٔ من</h2>
            <p class="rc-head-sub">
                آزمون‌های برگزارشدهٔ مشاور و کارنامه‌های شرکتی، در یک نگاه.
                <span class="rc-head-count">{{ persian_digits($summary['count']) }} کارنامه</span>
            </p>
        </div>
        @if($summary['average'] !== null)
            <div class="rc-head-badge">
                <span>میانگین کل</span>
                <strong>{{ $fa($summary['average']) }}<small>٪</small></strong>
            </div>
        @endif
    </header>

    @unless($report['has_data'])
        <section class="panel rc-empty">
            <span class="rc-empty-icon"><i class="fas fa-chart-pie" aria-hidden="true"></i></span>
            <h3>هنوز کارنامه‌ای ثبت نشده است</h3>
            <p>به‌محض برگزاری اولین آزمون، درصدها، روند پیشرفت و نقاط ضعف شما در همین صفحه نمایش داده می‌شود.</p>
        </section>
    @else
        {{-- 1 — hero: the newest score, the delta, and the headline facts --}}
        <section class="rc-hero" data-reveal>
            <div class="rc-hero-arc" style="--rc-value: {{ (float) ($summary['latest'] ?? 0) }}">
                @include('partials.report.arc', [
                    'value' => (float) ($summary['latest'] ?? 0),
                    'passLine' => $passLine,
                ])
                <div class="rc-hero-arc-copy">
                    <span>آخرین آزمون</span>
                    <strong>{{ $summary['latest_title'] }}</strong>
                    <small>{{ $summary['latest_source'] }}</small>
                </div>
            </div>

            <ul class="rc-hero-facts">
                <li>
                    <span class="rc-fact-label">تغییر نسبت به آزمون قبل</span>
                    <span class="rc-fact-value rc-delta rc-delta--{{ $trendTone }}">
                        @if($summary['delta'] === null)
                            —
                        @else
                            <i class="fas {{ $summary['delta'] >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' }}" aria-hidden="true"></i>
                            {{ $fa(abs($summary['delta'])) }} <small>واحد درصد</small>
                        @endif
                    </span>
                </li>
                <li>
                    <span class="rc-fact-label">بهترین / ضعیف‌ترین</span>
                    <span class="rc-fact-value">{{ $fa($summary['best']) }} <span class="rc-fact-sep">/</span> {{ $fa($summary['worst']) }}</span>                </li>
                <li>
                    <span class="rc-fact-label">بالای خط قبولی ({{ $fa($passLine) }}٪)</span>
                    <span class="rc-fact-value">{{ persian_digits($summary['above_line']) }} از {{ persian_digits($summary['scored']) }}</span>
                </li>
                <li>
                    <span class="rc-fact-label">حضور در آزمون‌ها</span>
                    <span class="rc-fact-value">
                        {{ persian_digits($summary['attended']) }} حضور
                        @if($summary['absences'] > 0)
                            <span class="rc-absent-pill">{{ persian_digits($summary['absences']) }} غیبت</span>
                        @endif
                    </span>
                </li>
            </ul>
        </section>

        <div class="rc-columns">
            {{-- 2 — trend --}}
            <section class="panel rc-panel rc-trend" data-reveal>
                <header class="rc-panel-head">
                    <h3><i class="fas fa-chart-line" aria-hidden="true"></i> روند پیشرفت</h3>
                    <span class="rc-panel-hint">هر نقطه یک آزمون، از قدیم به جدید</span>
                </header>
                @if(count($history) > 1)
                    <div class="rc-chart" role="img"
                         aria-label="نمودار درصد {{ count($history) }} آزمون، از قدیم به جدید">
                        <span class="rc-chart-line rc-chart-line--pass" style="--at: {{ 100 - $passLine }}%">
                            <em>خط قبولی {{ $fa($passLine) }}</em>
                        </span>
                        <span class="rc-chart-line rc-chart-line--mid" style="--at: 50%"></span>
                        <svg class="rc-chart-svg" viewBox="0 -6 100 106" preserveAspectRatio="none" aria-hidden="true">
                            {{-- Closed down to the bottom of the plot, so the wash sits UNDER the line. --}}
                            <polygon class="rc-chart-area" points="0,100 {{ $polyline }} 100,100"></polygon>
                            <polyline class="rc-chart-path" points="{{ $polyline }}"></polyline>
                        </svg>
                        @foreach($points as $i => $point)
                            <span class="rc-chart-dot {{ $history[count($history) - 1 - $i]['percent'] < $passLine ? 'is-below' : '' }}"
                                  style="--rc-x: {{ $point['x'] }}%; --rc-y: {{ $point['y'] }}%"
                                  title="{{ $history[count($history) - 1 - $i]['title'] }} — {{ $fa($history[count($history) - 1 - $i]['percent']) }}"></span>
                        @endforeach
                        <div class="rc-chart-axis">
                            @foreach(array_reverse($history) as $row)
                                <span>{{ $row['percent'] !== null ? $fa($row['percent']) : '—' }}</span>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="rc-note">برای دیدن نمودار، به حداقل دو آزمونِ تصحیح‌شده نیاز است.</p>
                @endif
            </section>

            {{-- 3 — per-subject standing --}}
            <section class="panel rc-panel rc-subjects" data-reveal>
                <header class="rc-panel-head">
                    <h3><i class="fas fa-layer-group" aria-hidden="true"></i> وضعیت درس‌ها</h3>
                    <span class="rc-panel-hint">آخرین درصد هر درس</span>
                </header>
                @if($report['subjects'])
                    <ul class="rc-subject-list">
                        @foreach($report['subjects'] as $subject)
                            <li class="rc-subject {{ $subject['below'] ? 'is-below' : 'is-above' }}">
                                <div class="rc-subject-top">
                                    <span class="rc-subject-name">{{ $subject['name'] }}</span>
                                    <span class="rc-subject-value">{{ $fa($subject['percent']) }}</span>
                                </div>
                                <div class="rc-subject-track">
                                    <span class="rc-subject-fill" style="--rc-value: {{ min(100, max(0, $subject['percent'])) }}%"></span>
                                    <span class="rc-subject-pass" style="--at: {{ min(100, max(0, $passLine)) }}%"></span>
                                </div>
                                <div class="rc-subject-meta">
                                    <span class="rc-trend-chip rc-trend-chip--{{ $subject['trend'] }}">
                                        <i class="fas {{ $subject['trend'] === 'up' ? 'fa-arrow-up' : ($subject['trend'] === 'down' ? 'fa-arrow-down' : 'fa-arrows-up-down') }}" aria-hidden="true"></i>
                                        @if($subject['overall_delta'] !== null)
                                            {{ $fa(abs($subject['overall_delta'])) }} از اولین آزمون
                                        @else
                                            اولین مشاهده
                                        @endif
                                    </span>
                                    @if($report['show_class_average'])
                                        <span class="rc-subject-class">مقایسه با میانگین شرکت‌کنندگان</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="rc-note">هنوز درصدی برای درس‌ها ثبت نشده است.</p>
                @endif
            </section>
        </div>

        {{-- 4 — needs attention: the newest value below the pass line --}}
        @if($report['weak'])
            <section class="panel rc-panel rc-weak" data-reveal>
                <header class="rc-panel-head">
                    <h3><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> نقاط ضعف این دوره</h3>
                    <span class="rc-panel-hint">هر درس زیر {{ $fa($passLine) }}٪، بر اساس آخرین آزمون</span>
                </header>
                <ul class="rc-weak-grid">
                    @foreach($report['weak'] as $subject)
                        <li class="rc-weak-card">
                            <div class="rc-weak-head">
                                <strong>{{ $subject['name'] }}</strong>
                                <span class="rc-weak-gap">{{ $fa(abs($subject['gap'])) }} واحد تا خط قبولی</span>
                            </div>
                            <div class="rc-weak-bar">
                                <span style="--rc-value: {{ min(100, max(0, $subject['percent'])) }}%"></span>
                            </div>
                            <p class="rc-weak-note">
                                <i class="fas fa-lightbulb" aria-hidden="true"></i>
                                این درس را در برنامهٔ هفته قرار بده و تمرین‌های همان فصل را دوباره بزن.
                            </p>
                            <span class="rc-weak-meta">{{ $subject['last_title'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- 5 — absences: a missed exam is part of the record --}}
        @if($report['absences'])
            <section class="panel rc-panel rc-absences" data-reveal>
                <header class="rc-panel-head">
                    <h3><i class="fas fa-user-slash" aria-hidden="true"></i> غیبت‌ها</h3>
                    <span class="rc-panel-hint">{{ persian_digits($report['summary']['absences']) }} آزمون برگزار شد و شما در آن نبودید</span>
                </header>
                <ul class="rc-absence-list">
                    @foreach($report['absences'] as $absence)
                        <li>
                            <span class="rc-absence-date"><time class="fa-date" datetime="{{ $absence['date_iso'] }}">{{ $absence['date_text'] }}</time></span>
                            <span class="rc-absence-title">{{ $absence['title'] }}</span>
                            <span class="rc-absence-source">{{ $absence['source_label'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- 6 — the row log --}}
        <section class="panel rc-panel" data-reveal>
            <header class="rc-panel-head">
                <h3><i class="fas fa-list-check" aria-hidden="true"></i> همهٔ کارنامه‌ها</h3>
                <span class="rc-panel-hint">از جدید به قدیم</span>
            </header>
            <ul class="rc-log">
                @foreach(array_reverse($report['rows']) as $row)
                    <li class="rc-log-row {{ $row['absent'] ? 'is-absent' : '' }}">
                        <span class="rc-log-source" style="@if(!empty($row['brand'])) --rc-brand: {{ $row['brand'] }}; @endif">
                            {{ $row['source_label'] }}
                        </span>
                        <span class="rc-log-main">
                            <strong>{{ $row['title'] }}</strong>
                            <small>
                                <time class="fa-date" datetime="{{ $row['date_iso'] }}">{{ $row['date_text'] }}</time>
                                @if($row['questions'])
                                    · {{ persian_digits($row['questions']) }} سوال
                                @endif
                            </small>
                        </span>
                        <span class="rc-log-score">
                            @if($row['absent'])
                                <span class="rc-absent-pill">غیبت</span>
                            @elseif($row['percent'] === null)
                                <span class="rc-pending-pill">در انتظار تصحیح</span>
                            @else
                                <strong class="{{ $row['percent'] < $passLine ? 'is-below' : '' }}">{{ $fa($row['percent']) }}</strong>
                                @if($row['rank'])
                                    <small>رتبه {{ persian_digits($row['rank']) }}</small>
                                @endif
                            @endif
                        </span>
                        @if($row['url'])
                            <a href="{{ $row['url'] }}" class="rc-log-open" aria-label="تحلیل این آزمون">
                                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endunless
</div>
@vite(['resources/js/features/student-report-card.js'])
@endsection
