{{--
    Teacher panel — the class timetable: recurring weekly blocks over the
    Persian week (Saturday first), with a <dialog> editor for create/edit.
    Server-rendered forms; the small teacher-panel.js bundle only opens and
    closes the dialog (and confirms deletes).
--}}
@extends('layouts.teacher')

@section('content')
<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-calendar-week"></i> برنامه کلاسی هفتگی</h2>
        <button type="button" class="primary-button draft-btn--icon" data-schedule-open aria-label="زنگ جدید">
            <i class="fas fa-plus"></i>
        </button>
    </header>

    @include('consultant.blog.flash')

    {{-- ── Time configuration ────────────────────────────────────── --}}
    <div class="teacher-schedule-config" data-schedule-config>
        <div class="settings-field">
            <span class="settings-field-label">از ساعت</span>
            <div class="popup-field">
                <input type="text" data-grid-start dir="ltr" inputmode="numeric" maxlength="5"
                       value="{{ persian_digits('08:00') }}" autocomplete="off" class="settings-input">
                <button type="button" class="popup-field-btn" data-popup="clock" aria-label="نمایش صفحه ساعت">
                    <i class="far fa-clock"></i>
                </button>
            </div>
        </div>
        <div class="settings-field">
            <span class="settings-field-label">تا ساعت</span>
            <div class="popup-field">
                <input type="text" data-grid-end dir="ltr" inputmode="numeric" maxlength="5"
                       value="{{ persian_digits('14:00') }}" autocomplete="off" class="settings-input">
                <button type="button" class="popup-field-btn" data-popup="clock" aria-label="نمایش صفحه ساعت">
                    <i class="far fa-clock"></i>
                </button>
            </div>
        </div>
        <div class="settings-field">
            <span class="settings-field-label">تعداد ستون</span>
            <input type="number" data-grid-cols min="1" max="12" value="6" class="settings-input">
        </div>
        <button type="button" class="secondary-button" data-grid-apply>
            <i class="fas fa-check"></i> اعمال
        </button>
    </div>

    {{-- ── Grid: days vertical (right), time horizontal (RTL) ────── --}}
    @php
        $activeDays = collect($dayLabels)->filter(fn ($label, $i) => $i <= 4); // Sat(0)–Wed(4)
        $gridStart = 8 * 60; // minutes
        $gridEnd   = 14 * 60;
        $gridCols  = 6;
        $colStep   = ($gridEnd - $gridStart) / $gridCols;
    @endphp

    @if($items->isNotEmpty())
        <div class="teacher-schedule-grid" data-schedule-grid
             style="--grid-start: {{ $gridStart }}; --grid-end: {{ $gridEnd }}; --grid-cols: {{ $gridCols }};">

            {{-- Header row: time slots read right-to-left --}}
            <div class="teacher-grid-header">
                <div class="teacher-grid-corner"></div>
                @for($c = 0; $c < $gridCols; $c++)
                    @php
                        $slotStart = $gridStart + ($c * $colStep);
                        $slotEnd   = $gridStart + (($c + 1) * $colStep);
                    @endphp
                    <div class="teacher-grid-slot-head">
                        <span dir="ltr">{{ persian_digits(sprintf('%02d:%02d', floor($slotStart / 60), $slotStart % 60)) }}</span>
                    </div>
                @endfor
            </div>

            {{-- Day rows --}}
            @foreach($activeDays as $dayIndex => $dayLabel)
                @php $dayItems = $items->get($dayIndex, collect()); @endphp
                <div class="teacher-grid-day @if($dayIndex === $todayDay) teacher-grid-day--today @endif">
                    <div class="teacher-grid-day-label">
                        <span>{{ $dayLabel }}</span>
                        @if($dayIndex === $todayDay)<span class="count-badge">امروز</span>@endif
                    </div>
                    @for($c = 0; $c < $gridCols; $c++)
                        @php
                            $slotStart = $gridStart + ($c * $colStep);
                            $slotEnd   = $gridStart + (($c + 1) * $colStep);
                            $cellItems = $dayItems->filter(function ($item) use ($slotStart, $slotEnd) {
                                $itemStart = (int) $item->start_time->format('H') * 60 + (int) $item->start_time->format('i');
                                $itemEnd   = (int) $item->end_time->format('H') * 60 + (int) $item->end_time->format('i');
                                return $itemStart < $slotEnd && $itemEnd > $slotStart;
                            });
                        @endphp
                        <div class="teacher-grid-cell" data-slot="{{ $c }}">
                            @foreach($cellItems as $item)
                                <div class="teacher-grid-card" style="--accent: {{ $item->color ?: 'var(--c-primary)' }}">
                                    <strong>{{ $item->title }}</strong>
                                    <span dir="ltr">{{ persian_digits($item->start_time->format('H:i')) }}–{{ persian_digits($item->end_time->format('H:i')) }}</span>
                                    @if($item->grade)<span class="teacher-grid-card-meta">{{ $item->grade }}</span>@endif
                                    <div class="teacher-grid-card-tools">
                                        <button
                                            type="button"
                                            class="topnav-icon-btn"
                                            data-schedule-open
                                            data-schedule-edit="{{ $item->id }}"
                                            data-edit-title="{{ $item->title }}"
                                            data-edit-subject="{{ $item->subject ?? '' }}"
                                            data-edit-grade="{{ $item->grade ?? '' }}"
                                            data-edit-day="{{ $item->day_of_week }}"
                                            data-edit-start="{{ $item->start_time->format('H:i') }}"
                                            data-edit-end="{{ $item->end_time->format('H:i') }}"
                                            data-edit-room="{{ $item->room ?? '' }}"
                                            data-edit-color="{{ $item->color ?? '#06B6D4' }}"
                                            data-edit-description="{{ $item->description ?? '' }}"
                                            data-edit-published="{{ $item->is_published ? '1' : '0' }}"
                                            aria-label="ویرایش"
                                        ><i class="fas fa-pen"></i></button>
                                        <form method="POST" action="{{ route('teacher.schedule.destroy', $item) }}" data-confirm="این زنگ حذف شود؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="topnav-icon-btn" aria-label="حذف"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endfor
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="far fa-calendar"></i>
            <h3>برنامه‌ای ثبت نشده</h3>
            <p>زنگ‌های هفتگی کلاس‌های خود را اضافه کنید تا دانش‌آموزان جدول کلاس را ببینند.</p>
        </div>
    @endif

    {{-- ── Grade/class grouped list below the grid ──────────────── --}}
    @php
        $byGrade = $items->flatten(1)->groupBy(fn ($item) => $item->grade ?? 'عمومی');
    @endphp

    @if($items->isNotEmpty())
        <div class="teacher-grade-sections">
            @foreach($byGrade as $gradeName => $gradeItems)
                <section class="teacher-grade-block">
                    <h3 class="teacher-grade-head">
                        <i class="fas fa-layer-group"></i>
                        <span>{{ $gradeName === 'عمومی' ? 'همه پایه‌ها' : $gradeName }}</span>
                    </h3>
                    @php $byClass = $gradeItems->groupBy(fn ($i) => $i->title ?? 'بدون عنوان'); @endphp
                    @foreach($byClass as $className => $classItems)
                        <div class="teacher-class-block">
                            <h4 class="teacher-class-block-title">{{ $className }}</h4>
                            <div class="teacher-class-list">
                                @foreach($classItems->sortBy(['day_of_week', 'start_time']) as $item)
                                    <div class="teacher-class-row" style="--accent: {{ $item->color ?: 'var(--c-primary)' }}">
                                        <span class="teacher-class-time" dir="ltr">
                                            {{ persian_digits($item->start_time->format('H:i')) }} – {{ persian_digits($item->end_time->format('H:i')) }}
                                        </span>
                                        <div class="teacher-class-body">
                                            <strong>
                                                {{ $item->title }}
                                                @if(! $item->is_published)
                                                    <span class="exam-status exam-status--missed">پیش‌نویس</span>
                                                @endif
                                            </strong>
                                            <span class="teacher-class-meta">
                                                @if($item->grade)<span><i class="fas fa-layer-group"></i> {{ $item->grade }}</span>@endif
                                                @if($item->room)<span><i class="fas fa-location-dot"></i> {{ $item->room }}</span>@endif
                                                <span><i class="fas fa-calendar-day"></i> {{ $item->dayLabel() }}</span>
                                            </span>
                                        </div>
                                        <div class="teacher-class-tools">
                                            <button
                                                type="button"
                                                class="topnav-icon-btn"
                                                data-schedule-open
                                                data-schedule-edit="{{ $item->id }}"
                                                data-edit-title="{{ $item->title }}"
                                                data-edit-subject="{{ $item->subject ?? '' }}"
                                                data-edit-grade="{{ $item->grade ?? '' }}"
                                                data-edit-day="{{ $item->day_of_week }}"
                                                data-edit-start="{{ $item->start_time->format('H:i') }}"
                                                data-edit-end="{{ $item->end_time->format('H:i') }}"
                                                data-edit-room="{{ $item->room ?? '' }}"
                                                data-edit-color="{{ $item->color ?? '#06B6D4' }}"
                                                data-edit-description="{{ $item->description ?? '' }}"
                                                data-edit-published="{{ $item->is_published ? '1' : '0' }}"
                                                aria-label="ویرایش"
                                            ><i class="fas fa-pen"></i></button>
                                            <form method="POST" action="{{ route('teacher.schedule.destroy', $item) }}" data-confirm="این زنگ حذف شود؟">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="topnav-icon-btn" aria-label="حذف"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</div>

<dialog class="teacher-dialog teacher-dialog--animated" id="schedule-dialog" data-schedule-dialog>
    {{-- The action/method are rewritten by teacher-panel.js for edits;
         verbatim they create a new item. --}}
    <form
        method="POST"
        action="{{ route('teacher.schedule.store') }}"
        data-update-route="{{ route('teacher.schedule.update', ['item' => '__ID__']) }}"
        class="teacher-form"
        data-schedule-form
    >
        @csrf
        <div class="teacher-dialog-head">
            <h3 class="teacher-dialog-title" data-schedule-title>زنگ جدید</h3>
            <label class="teacher-csv-import">
                <input type="file" accept=".csv" data-csv-input hidden aria-label="وارد کردن CSV">
                <span class="topnav-icon-btn" aria-hidden="true">
                    <i class="fas fa-file-csv"></i>
                </span>
            </label>
        </div>

        <div class="teacher-form-grid">
            <label class="settings-field">
                <span class="settings-field-label">عنوان / کلاس</span>
                <input type="text" name="title" class="settings-input" required>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">درس</span>
                <input type="text" name="subject" list="schedule-subjects" class="settings-input">
                <datalist id="schedule-subjects">
                    @foreach($subjects as $subject)
                        <option value="{{ $subject }}"></option>
                    @endforeach
                </datalist>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">پایه (خالی = همه پایه‌ها)</span>
                <select name="grade" class="settings-input">
                    <option value="">همه پایه‌ها</option>
                    @foreach($gradeOptions as $gradeValue => $gradeLabel)
                        <option value="{{ $gradeValue }}">{{ $gradeLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">روز هفته</span>
                <select name="day_of_week" class="settings-input">
                    @foreach($dayLabels as $dayIndex => $dayLabel)
                        <option value="{{ $dayIndex }}">{{ $dayLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="settings-field field-with-popup">
                <span class="settings-field-label">از ساعت</span>
                <div class="popup-field">
                    <input type="text" name="start_time" dir="ltr" inputmode="numeric" maxlength="5"
                           autocomplete="off" class="settings-input" required>
                    <button type="button" class="popup-field-btn" data-popup="clock" aria-label="نمایش صفحه ساعت">
                        <i class="far fa-clock"></i>
                    </button>
                </div>
            </label>

            <label class="settings-field field-with-popup">
                <span class="settings-field-label">تا ساعت</span>
                <div class="popup-field">
                    <input type="text" name="end_time" dir="ltr" inputmode="numeric" maxlength="5"
                           autocomplete="off" class="settings-input" required>
                    <button type="button" class="popup-field-btn" data-popup="clock" aria-label="نمایش صفحه ساعت">
                        <i class="far fa-clock"></i>
                    </button>
                </div>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">کلاس / محل</span>
                <input type="text" name="room" class="settings-input">
            </label>

            <div class="settings-field">
                <span class="settings-field-label">رنگ (اختیاری)</span>
                <input type="hidden" name="color" value="#06B6D4" data-color-value>
                <div class="teacher-color-picker" data-color-picker>
                    @foreach(['#06B6D4', '#3B82F6', '#22C55E', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'] as $hex)
                        <button type="button" class="color-swatch{{ $hex === '#06B6D4' ? ' selected' : '' }}"
                                data-color="{{ $hex }}" style="--swatch: {{ $hex }}" aria-label="{{ $hex }}"></button>
                    @endforeach
                </div>
            </div>
        </div>

        <label class="settings-field">
            <span class="settings-field-label">توضیح (اختیاری)</span>
            <textarea name="description" rows="2" class="settings-input"></textarea>
        </label>

        <div class="teacher-form-actions">
            <label class="teacher-switch">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1" checked>
                <span>نمایش در جدول دانش‌آموزان</span>
            </label>
            <div>
                <button type="button" class="secondary-button" data-schedule-cancel>انصراف</button>
                <button type="submit" class="primary-button"><i class="fas fa-check"></i> ذخیره</button>
            </div>
        </div>
    </form>
</dialog>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
