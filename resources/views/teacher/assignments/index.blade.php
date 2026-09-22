{{--
    Teacher panel — assignment list with per-assignment status counts. The
    create form lives in an in-page <dialog> (teacher-panel.js opens it),
    matching the schedule screen's editor pattern.
--}}
@extends('layouts.teacher')

@section('content')
<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-tasks"></i> تکالیف من</h2>
        <button type="button" class="primary-button draft-btn--icon" data-assignment-open aria-label="تکلیف جدید">
            <i class="fas fa-plus"></i>
        </button>
    </header>

    @include('consultant.blog.flash')

    @if($assignments->isNotEmpty())
        <div class="exam-grid" data-stagger>
            @foreach($assignments as $assignment)
                <article class="exam-card">
                    <div class="exam-card-icon exam-card-icon--comprehensive">
                        @if(! $assignment->is_published)
                            <span class="exam-status exam-status--missed">پیش‌نویس</span>
                        @endif
                        <i class="fas fa-clipboard-list teacher-material-icon"></i>
                        <span class="exam-card-icon-label">تکلیف</span>
                    </div>
                    <div class="exam-card-body">
                        <h3 class="exam-card-title">{{ $assignment->title }}</h3>
                        <ul class="exam-card-facts">
                            <li><i class="fas fa-layer-group"></i>{{ $assignment->grade ?: 'همه پایه‌ها' }}@if($assignment->classroom) — {{ $assignment->classroom->name }}@endif</li>
                            @if($assignment->subject)
                                <li><i class="fas fa-book"></i>{{ $assignment->subject }}</li>
                            @endif
                            <li><i class="fas fa-users"></i>{{ persian_digits($assignment->submissions_count) }} پاسخ ثبت‌شده</li>
                            @if($assignment->due_at)
                                <li><i class="fas fa-calendar-day"></i>
                                    <time class="fa-date" datetime="{{ $assignment->due_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($assignment->due_at->format('Y/m/d')) }}</time>
                                </li>
                            @endif
                        </ul>

                        <div class="teacher-status-strip">
                            <span class="status-pill status-pill--submitted">
                                {{ persian_digits((int) ($submittedCounts[$assignment->id] ?? 0)) }} در انتظار بررسی
                            </span>
                        </div>

                        <div class="teacher-material-actions">
                            <a href="{{ route('teacher.assignments.show', $assignment) }}" class="topnav-icon-btn" aria-label="وضعیت دانش‌آموزان" title="وضعیت دانش‌آموزان">
                                <i class="fas fa-list-check"></i>
                            </a>
                            <form method="POST" action="{{ route('teacher.assignments.destroy', $assignment) }}" data-confirm="این تکلیف و همه وضعیت‌های آن حذف شود؟">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger-button"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="far fa-tasks"></i>
            <h3>هنوز تکلیفی نساخته‌اید</h3>
            <p>با دکمهٔ ＋ اولین تکلیف کلاس را بسازید.</p>
        </div>
    @endif

    {{-- Classroom picker source: grade => {id: name}, so the dialog can
         enable the select only once a grade is picked. --}}
    <div hidden data-classroom-options='@json($classrooms)'></div>

    <dialog class="teacher-dialog teacher-dialog--animated" id="assignment-dialog" data-assignment-dialog @if($errors->any()) data-open-on-load @endif>
        <form method="POST" action="{{ route('teacher.assignments.store') }}" enctype="multipart/form-data" class="teacher-form" data-assignment-form>
            @csrf
            <div class="teacher-dialog-head">
                <h3 class="teacher-dialog-title">تکلیف جدید</h3>
            </div>

            <div class="teacher-form-grid">
                <label class="settings-field">
                    <span class="settings-field-label">عنوان</span>
                    <input type="text" name="title" value="{{ old('title') }}" class="settings-input @error('title') is-invalid @enderror" required>
                    @error('title')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">درس</span>
                    <input type="text" name="subject" value="{{ old('subject') }}" list="teacher-subjects" class="settings-input @error('subject') is-invalid @enderror">
                    <datalist id="teacher-subjects">
                        @foreach($subjects as $subject)
                            <option value="{{ $subject }}"></option>
                        @endforeach
                    </datalist>
                    @error('subject')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">پایه</span>
                    <select name="grade" class="settings-input @error('grade') is-invalid @enderror" data-grade-select required>
                        <option value="">انتخاب پایه…</option>
                        @foreach($gradeOptions as $gradeValue => $gradeLabel)
                            <option value="{{ $gradeValue }}" @selected(old('grade') === $gradeValue)>{{ $gradeLabel }}</option>
                        @endforeach
                    </select>
                    @error('grade')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">کلاس (اختیاری)</span>
                    <select name="classroom_id" class="settings-input @error('classroom_id') is-invalid @enderror" data-classroom-select data-selected="{{ old('classroom_id') }}" disabled>
                        <option value="">همه کلاس‌های این پایه</option>
                    </select>
                    @error('classroom_id')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">مهلت تحویل (اختیاری)</span>
                    <input type="date" name="due_at" value="{{ old('due_at') }}" dir="ltr" class="settings-input @error('due_at') is-invalid @enderror">
                    @error('due_at')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">نمره بیشینه (اختیاری)</span>
                    <input type="number" name="max_score" value="{{ old('max_score') }}" min="0" step="0.5" dir="ltr" class="settings-input @error('max_score') is-invalid @enderror">
                    @error('max_score')<span class="settings-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <label class="settings-field">
                <span class="settings-field-label">توضیح تکلیف</span>
                <textarea name="description" rows="5" class="settings-input @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">فایل پیوست (اختیاری — عکس یا PDF)</span>
                <label class="file-picker">
                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                    <input type="file" name="file" accept="image/*,.pdf">
                </label>
                @error('file')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <div class="teacher-form-actions">
                <label class="teacher-switch">
                    <input type="hidden" name="is_published" value="0">
                    <input type="checkbox" name="is_published" value="1" checked>
                    <span>انتشار برای کلاس</span>
                </label>
                <div>
                    <button type="button" class="secondary-button" data-assignment-cancel>انصراف</button>
                    <button type="submit" class="primary-button"><i class="fas fa-check"></i> ایجاد تکلیف</button>
                </div>
            </div>
        </form>
    </dialog>
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
