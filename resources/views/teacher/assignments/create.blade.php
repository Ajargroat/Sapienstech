{{--
    Teacher panel — create a new assignment. Grade picker follows the
    config-driven academics vocabulary (middle school first, high school
    available); grade empty = every grade of the academy.
--}}
@extends('layouts.teacher')

@section('content')
<a href="{{ route('teacher.assignments.index') }}" class="teacher-backlink">
    <i class="fas fa-arrow-right"></i> بازگشت به تکالیف
</a>

<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-plus"></i> تکلیف جدید</h2>
    </header>

    @include('consultant.blog.flash')

    <form method="POST" action="{{ route('teacher.assignments.store') }}" class="teacher-form">
        @csrf

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
                <span class="settings-field-label">پایه (خالی = همه پایه‌ها)</span>
                <select name="grade" class="settings-input @error('grade') is-invalid @enderror">
                    <option value="">همه پایه‌ها</option>
                    @foreach($gradeOptions as $gradeValue => $gradeLabel)
                        <option value="{{ $gradeValue }}" @selected(old('grade') === $gradeValue)>{{ $gradeLabel }}</option>
                    @endforeach
                </select>
                @error('grade')<span class="settings-error">{{ $message }}</span>@enderror
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

        <div class="teacher-form-actions">
            <label class="teacher-switch">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1" checked>
                <span>انتشار برای کلاس</span>
            </label>
            <button type="submit" class="primary-button"><i class="fas fa-check"></i> ایجاد تکلیف</button>
        </div>
    </form>
</div>
@endsection
