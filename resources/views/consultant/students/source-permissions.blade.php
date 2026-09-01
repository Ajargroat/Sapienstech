{{--
    PROTOTYPE: consultant source-permission page for a single student.

    Rendered by App\Http\Controllers\Consultant\StudentSourcePermissionController::index()
    through the `consultant.student.source-permissions` route. Grants are sample
    data in the controller (no Source/Grant models yet); this page validates the
    routing / view wiring and previews what the real feature needs: the
    study-vs-assessment pairing model (study book A, tested from book B so
    questions stay fresh), a searchable/filterable grants table, progress
    tracking and a grant-access dialog.
--}}
@extends('layouts.consultant')

@section('content')
<div id="source-permissions-app">
    <style>
        #source-permissions-app .sp-note { display: flex; gap: 12px; align-items: flex-start; background: color-mix(in srgb, var(--c-info) 8%, transparent); border: 1px solid color-mix(in srgb, var(--c-info) 25%, transparent); border-radius: var(--radius-card); padding: 14px 18px; margin-bottom: 24px; }
        #source-permissions-app .sp-note i { color: var(--c-info); font-size: 16px; margin-top: 3px; }
        #source-permissions-app .sp-note p { margin: 0; font-size: 13.5px; color: var(--c-text); line-height: 1.9; }

        #source-permissions-app .sp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 32px; }
        #source-permissions-app .sp-stat { background: var(--c-surface); border: 1px solid var(--c-border); border-radius: var(--radius-card); padding: 18px 20px; display: flex; flex-direction: column; gap: 4px; }
        #source-permissions-app .sp-stat-icon { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 10px; }
        #source-permissions-app .sp-stat-icon--active { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); }
        #source-permissions-app .sp-stat-icon--study { color: var(--c-secondary); background: color-mix(in srgb, var(--c-secondary) 12%, transparent); }
        #source-permissions-app .sp-stat-icon--exam { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); }
        #source-permissions-app .sp-stat-icon--expiring { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); }
        #source-permissions-app .sp-stat-value { font-size: 28px; font-weight: 800; color: var(--c-text); line-height: 1.1; }
        #source-permissions-app .sp-stat-label { font-size: 13px; color: var(--c-muted); }

        #source-permissions-app .sp-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        #source-permissions-app .sp-tab { display: inline-flex; align-items: center; gap: 7px; padding: 6px 14px; border-radius: 999px; border: 1px solid var(--c-border); background: var(--c-surface); color: var(--c-muted); font-size: 13px; font-weight: 600; transition: all var(--animation-duration) ease; }
        #source-permissions-app .sp-tab span { font-size: 11px; padding: 1px 7px; border-radius: 999px; background: var(--c-surface-alt); }
        #source-permissions-app .sp-tab:hover { border-color: var(--c-border-strong); color: var(--c-text); }
        #source-permissions-app .sp-tab.is-active { background: var(--c-primary); border-color: var(--c-primary); color: #fff; }
        #source-permissions-app .sp-tab.is-active span { background: rgba(255, 255, 255, .2); color: #fff; }

        #source-permissions-app .sp-panel { padding: 0; overflow: hidden; margin-bottom: 32px; }
        #source-permissions-app .sp-table-wrap { overflow-x: auto; }
        #source-permissions-app .sp-table { width: 100%; border-collapse: collapse; min-width: 860px; }
        #source-permissions-app .sp-pair-table { min-width: 640px; }
        #source-permissions-app .sp-table th { text-align: right; font-size: 12px; font-weight: 700; color: var(--c-muted); padding: 14px 18px; border-bottom: 1px solid var(--c-border); }
        #source-permissions-app .sp-table td { padding: 14px 18px; border-bottom: 1px solid var(--c-border); font-size: 14px; color: var(--c-text); vertical-align: middle; }
        #source-permissions-app .sp-table tbody tr:last-child td { border-bottom: none; }
        #source-permissions-app .sp-table tbody tr { transition: background var(--animation-duration) ease; }
        #source-permissions-app .sp-table tbody tr:hover { background: var(--c-surface-alt); }

        #source-permissions-app .sp-source-cell { display: flex; flex-direction: column; gap: 3px; }
        #source-permissions-app .sp-source { font-weight: 700; }
        #source-permissions-app .sp-source-cell small { font-size: 12px; color: var(--c-muted); }

        #source-permissions-app .sp-kind,
        #source-permissions-app .sp-status,
        #source-permissions-app .sp-state { display: inline-block; padding: 3px 11px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        #source-permissions-app .sp-state { display: inline-flex; align-items: center; gap: 6px; }
        #source-permissions-app .sp-kind--study { color: var(--c-secondary); background: color-mix(in srgb, var(--c-secondary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-secondary) 25%, transparent); }
        #source-permissions-app .sp-kind--exam { color: var(--c-info); background: color-mix(in srgb, var(--c-info) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-info) 25%, transparent); }
        #source-permissions-app .sp-status--active { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent); }
        #source-permissions-app .sp-status--expiring { color: var(--c-primary); background: color-mix(in srgb, var(--c-primary) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-primary) 25%, transparent); }
        #source-permissions-app .sp-status--expired { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-danger) 25%, transparent); }
        #source-permissions-app .sp-status--paused { color: var(--c-muted); background: var(--c-surface-alt); border: 1px solid var(--c-border); }
        #source-permissions-app .sp-state--fresh { color: var(--c-success); background: color-mix(in srgb, var(--c-success) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-success) 25%, transparent); }
        #source-permissions-app .sp-state--overlap { color: var(--c-danger); background: color-mix(in srgb, var(--c-danger) 12%, transparent); border: 1px solid color-mix(in srgb, var(--c-danger) 25%, transparent); }
        #source-permissions-app .sp-state--missing { color: var(--c-muted); background: var(--c-surface-alt); border: 1px solid var(--c-border); }

        #source-permissions-app .sp-period small { display: block; color: var(--c-muted); font-size: 12px; }
        #source-permissions-app .sp-progress { display: flex; align-items: center; gap: 10px; }
        #source-permissions-app .sp-progress strong { font-size: 13px; min-width: 38px; }
        #source-permissions-app .sp-bar { width: 90px; height: 8px; border-radius: 999px; background: var(--c-surface-alt); border: 1px solid var(--c-border); overflow: hidden; }
        #source-permissions-app .sp-bar span { display: block; height: 100%; border-radius: 999px; }
        #source-permissions-app .sp-bar--active span { background: var(--c-success); }
        #source-permissions-app .sp-bar--expiring span { background: var(--c-primary); }
        #source-permissions-app .sp-bar--expired span { background: var(--c-danger); }
        #source-permissions-app .sp-bar--paused span { background: var(--c-muted); }

        #source-permissions-app .sp-action { font-size: 13px; font-weight: 600; color: var(--c-primary); white-space: nowrap; }
        #source-permissions-app .sp-action:hover { color: var(--c-primary-hover); text-decoration: underline; }
        #source-permissions-app .sp-action--danger { color: var(--c-danger); margin-inline-start: 14px; }
        #source-permissions-app .sp-action--danger:hover { color: var(--c-danger); }

        #source-permissions-app .sp-modal { border: 1px solid var(--c-border); border-radius: var(--radius-card); background: var(--c-surface); color: var(--c-text); padding: 0; width: min(560px, calc(100% - 32px)); }
        #source-permissions-app .sp-modal::backdrop { background: rgba(0, 0, 0, .55); backdrop-filter: blur(4px); }
        #source-permissions-app .sp-modal-head { padding: 20px 24px 0; }
        #source-permissions-app .sp-modal-head h3 { margin: 0; font-size: 19px; font-weight: 800; }
        #source-permissions-app .sp-modal-head h3 i { color: var(--c-primary); margin-inline-end: 6px; }
        #source-permissions-app .sp-modal-head p { margin: 6px 0 0; font-size: 12.5px; color: var(--c-muted); }
        #source-permissions-app .sp-modal-body { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; padding: 20px 24px; }
        #source-permissions-app .sp-field { display: flex; flex-direction: column; gap: 6px; }
        #source-permissions-app .sp-field--wide { grid-column: 1 / -1; }
        #source-permissions-app .sp-field label { font-size: 12.5px; font-weight: 600; color: var(--c-muted); }
        #source-permissions-app .sp-field select,
        #source-permissions-app .sp-field input,
        #source-permissions-app .sp-field textarea { background: var(--c-surface-alt); border: 1px solid var(--c-border); border-radius: 10px; padding: 9px 12px; color: var(--c-text); font: inherit; font-size: 14px; }
        #source-permissions-app .sp-field select:focus,
        #source-permissions-app .sp-field input:focus,
        #source-permissions-app .sp-field textarea:focus { outline: none; border-color: var(--c-primary); }
        #source-permissions-app .sp-modal-foot { display: flex; gap: 10px; padding: 0 24px 20px; }
        #source-permissions-app .sp-modal-foot .primary-button[disabled] { opacity: .5; cursor: not-allowed; }
        @media (max-width: 680px) {
            #source-permissions-app .sp-modal-body { grid-template-columns: 1fr; }
        }
    </style>

    <div class="dashboard-header">
        <div>
            <h1>دسترسی منابع {{ $student->name }}</h1>
            <p>
                <a href="{{ route('consultant.student.profile', $student) }}" class="secondary-button">
                    <i class="fas fa-arrow-right"></i> بازگشت به پروفایل {{ $student->name }}
                </a>
            </p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="primary-button" onclick="document.getElementById('grant-source-modal').showModal()">
                <i class="fas fa-plus"></i> اعطای دسترسی جدید
            </button>
        </div>
    </div>

    <section class="sp-note">
        <i class="fas fa-lightbulb"></i>
        <p>
            <strong>مدل دسترسی:</strong> دسترسی مطالعه و دسترسی آزمون از هم جدا هستند. می‌توانید
            {{ $student->name }} را با «کتاب A» برای مطالعه و «کتاب B» برای آزمون دسترسی بدهید تا
            سوالات آزمون تازه بمانند. جدول پایین نشان می‌دهد این جفت‌سازی برای هر درس چگونه چیده شده است.
        </p>
    </section>

    <section class="sp-stats">
        <div class="sp-stat">
            <span class="sp-stat-icon sp-stat-icon--active"><i class="fas fa-toggle-on"></i></span>
            <span class="sp-stat-value">{{ persian_digits($stats['active']) }}</span>
            <span class="sp-stat-label">دسترسی فعال</span>
        </div>
        <div class="sp-stat">
            <span class="sp-stat-icon sp-stat-icon--study"><i class="fas fa-book-reader"></i></span>
            <span class="sp-stat-value">{{ persian_digits($stats['study']) }}</span>
            <span class="sp-stat-label">منبع مطالعه</span>
        </div>
        <div class="sp-stat">
            <span class="sp-stat-icon sp-stat-icon--exam"><i class="fas fa-clipboard-list"></i></span>
            <span class="sp-stat-value">{{ persian_digits($stats['exam']) }}</span>
            <span class="sp-stat-label">منبع آزمون</span>
        </div>
        <div class="sp-stat">
            <span class="sp-stat-icon sp-stat-icon--expiring"><i class="fas fa-hourglass-half"></i></span>
            <span class="sp-stat-value">{{ persian_digits($stats['expiring']) }}</span>
            <span class="sp-stat-label">رو به اتمام</span>
        </div>
    </section>

    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>جفت مطالعه و سنجش</h2>
            <span class="count-badge">{{ persian_digits($pairings->count()) }} درس</span>
        </div>
    </div>

    <section class="panel sp-panel">
        <div class="sp-table-wrap">
            <table class="sp-table sp-pair-table">
                <thead>
                    <tr>
                        <th>درس</th>
                        <th>منبع مطالعه</th>
                        <th>منبع آزمون</th>
                        <th>تازگی سوالات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pairings as $pair)
                        <tr>
                            <td class="sp-source">{{ $pair['lesson'] }}</td>
                            <td>{{ $pair['study'] ?? '—' }}</td>
                            <td>{{ $pair['exam'] ?? '—' }}</td>
                            <td>
                                <span class="sp-state sp-state--{{ $pair['state'] }}">
                                    <i class="fas @switch($pair['state'])
                                        @case('fresh') fa-bolt @break
                                        @case('overlap') fa-exclamation-triangle @break
                                        @default fa-plus-circle
                                    @endswitch"></i>
                                    {{ $states[$pair['state']] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="panel-heading">
        <div class="panel-heading-title">
            <h2>همه دسترسی‌ها</h2>
            <span class="count-badge">{{ persian_digits($grants->count()) }} دسترسی</span>
        </div>

        <div class="panel-heading-actions">
            <div class="search-reveal @if($search !== '') is-open @endif">
                <form method="GET" action="{{ route('consultant.student.source-permissions', $student) }}" class="search-reveal-form">
                    <button type="submit" class="search-reveal-toggle" aria-label="جستجو">
                        <i class="fas fa-search"></i>
                    </button>
                    <input type="text" name="search" value="{{ $search }}" placeholder="جستجوی منبع یا درس…" class="search-reveal-input" aria-label="جستجوی منبع یا درس">
                    <input type="hidden" name="status" value="{{ $status }}">
                    @if($search !== '')
                        <a href="{{ route('consultant.student.source-permissions', ['student' => $student, 'status' => $status !== '' ? $status : null]) }}" class="search-reveal-clear" aria-label="پاک کردن جستجو">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </div>

    <nav class="sp-tabs" aria-label="فیلتر وضعیت دسترسی‌ها">
        <a href="{{ route('consultant.student.source-permissions', ['student' => $student, 'search' => $search !== '' ? $search : null]) }}"
           class="sp-tab @if($status === '') is-active @endif">
            همه <span>{{ persian_digits($stats['total']) }}</span>
        </a>
        @foreach($statuses as $key => $label)
            <a href="{{ route('consultant.student.source-permissions', ['student' => $student, 'status' => $key, 'search' => $search !== '' ? $search : null]) }}"
               class="sp-tab @if($status === $key) is-active @endif">
                {{ $label }} <span>{{ persian_digits($statusCounts[$key] ?? 0) }}</span>
            </a>
        @endforeach
    </nav>

    @if($grants->isNotEmpty())
        <section class="panel sp-panel">
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>منبع</th>
                            <th>نوع دسترسی</th>
                            <th>وضعیت</th>
                            <th>بازه دسترسی</th>
                            <th>پیشرفت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grants as $grant)
                            <tr>
                                <td>
                                    <div class="sp-source-cell">
                                        <span class="sp-source">{{ $grant['source'] }}</span>
                                        <small>{{ $grant['lesson'] }} · {{ $grant['type'] }}</small>
                                    </div>
                                </td>
                                <td><span class="sp-kind sp-kind--{{ $grant['kind'] }}">{{ $kinds[$grant['kind']] }}</span></td>
                                <td><span class="sp-status sp-status--{{ $grant['status'] }}">{{ $statuses[$grant['status']] }}</span></td>
                                <td class="sp-period">
                                    {{ $grant['from'] }}
                                    <small>تا {{ $grant['until'] }}</small>
                                </td>
                                <td>
                                    <div class="sp-progress">
                                        <div class="sp-bar sp-bar--{{ $grant['status'] }}">
                                            <span style="width: {{ $grant['progress'] }}%"></span>
                                        </div>
                                        <strong>{{ persian_digits($grant['progress']) }}٪</strong>
                                    </div>
                                </td>
                                <td>
                                    <a href="#" class="sp-action">تمدید</a>
                                    <a href="#" class="sp-action sp-action--danger">لغو</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="panel">
            <div class="empty-state">
                @if($search !== '')
                    <i class="fas fa-search"></i>
                    <h3>دسترسی‌ای با این جستجو پیدا نشد</h3>
                    <p>عبارت «{{ $search }}» را با نام منبع یا درس تغییر دهید.</p>
                @elseif($status !== '')
                    <i class="fas fa-filter"></i>
                    <h3>دسترسی «{{ $statuses[$status] }}» وجود ندارد</h3>
                    <p>برای دیدن همه دسترسی‌ها تب «همه» را انتخاب کنید.</p>
                @else
                    <i class="fas fa-shield-alt"></i>
                    <h3>هنوز دسترسی‌ای ثبت نشده است</h3>
                    <p>با دکمه «اعطای دسترسی جدید» اولین منبع را به {{ $student->name }} اختصاص دهید.</p>
                @endif
            </div>
        </section>
    @endif

    <dialog id="grant-source-modal" class="sp-modal">
        <form method="dialog">
            <div class="sp-modal-head">
                <h3><i class="fas fa-shield-alt"></i> اعطای دسترسی جدید</h3>
                <p>منبع مطالعه و منبع آزمون می‌توانند جدا باشند؛ این‌طور سوالات آزمون تازه می‌مانند.</p>
            </div>
            <div class="sp-modal-body">
                <div class="sp-field">
                    <label for="grant-source">منبع اصلی</label>
                    <select id="grant-source" name="source">
                        @foreach($sources as $source)
                            <option>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sp-field">
                    <label for="grant-kind">نوع دسترسی</label>
                    <select id="grant-kind" name="kind">
                        @foreach($kinds as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sp-field">
                    <label for="grant-from">تاریخ شروع</label>
                    <input type="text" id="grant-from" name="from" placeholder="۱۴۰۴/۰۶/۰۱">
                </div>
                <div class="sp-field">
                    <label for="grant-duration">مدت دسترسی</label>
                    <select id="grant-duration" name="duration">
                        <option>یک‌ماهه</option>
                        <option>سه‌ماهه</option>
                        <option>تا پایان ترم</option>
                        <option>بدون انقضا</option>
                    </select>
                </div>
                <div class="sp-field sp-field--wide">
                    <label for="grant-exam-source">منبع آزمون متصل (برای سوالات تازه — اختیاری)</label>
                    <select id="grant-exam-source" name="exam_source">
                        <option value="">بدون آزمون متصل</option>
                        @foreach($sources as $source)
                            <option>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sp-field sp-field--wide">
                    <label for="grant-note">توضیح برای دانش‌آموز</label>
                    <textarea id="grant-note" name="note" rows="3" placeholder="مثلاً: فصل‌های ۱ تا ۴ زیست تا هفته آینده مطالعه شود."></textarea>
                </div>
            </div>
            <div class="sp-modal-foot">
                <button type="button" class="secondary-button" onclick="document.getElementById('grant-source-modal').close()">انصراف</button>
                <button type="submit" class="primary-button" disabled>اعطای دسترسی</button>
            </div>
        </form>
    </dialog>
</div>
@endsection
