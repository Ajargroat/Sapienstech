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
        <button type="button" class="primary-button" data-schedule-open>
            <i class="fas fa-plus"></i> زنگ جدید
        </button>
    </header>

    @include('consultant.blog.flash')

    @if($items->isNotEmpty())
        <div class="student-side">
            @foreach($dayLabels as $dayIndex => $dayLabel)
                @php $dayItems = $items->get($dayIndex, collect()); @endphp
                <section class="sched-day @if($dayIndex === $todayDay) sched-day--today @endif">
                    <h3 class="sched-day-head">
                        <span>{{ $dayLabel }}</span>
                        @if($dayIndex === $todayDay)<span class="count-badge">امروز</span>@endif
                    </h3>

                    @if($dayItems->isNotEmpty())
                        <div class="teacher-class-list">
                            @foreach($dayItems as $item)
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
                    @else
                        <p class="teacher-day-empty">—</p>
                    @endif
                </section>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="far fa-calendar"></i>
            <h3>برنامه‌ای ثبت نشده</h3>
            <p>زنگ‌های هفتگی کلاس‌های خود را اضافه کنید تا دانش‌آموزان جدول کلاس را ببینند.</p>
        </div>
    @endif
</div>

<dialog class="teacher-dialog" id="schedule-dialog" data-schedule-dialog>
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
        <h3 class="teacher-dialog-title" data-schedule-title>زنگ جدید</h3>

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

            <label class="settings-field">
                <span class="settings-field-label">از ساعت</span>
                <input type="time" name="start_time" dir="ltr" class="settings-input" required>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">تا ساعت</span>
                <input type="time" name="end_time" dir="ltr" class="settings-input" required>
            </label>

            <label class="settings-field">
                <span class="settings-field-label">کلاس / محل</span>
                <input type="text" name="room" class="settings-input">
            </label>

            <label class="settings-field">
                <span class="settings-field-label">رنگ (اختیاری)</span>
                <input type="color" name="color" value="#06B6D4" dir="ltr" class="settings-input">
            </label>
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
