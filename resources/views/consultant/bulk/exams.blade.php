@extends('consultant.bulk.layout')

@section('bulk-content')
<form method="POST" action="{{ route('consultant.bulk.exams.store') }}" class="bulk-form" data-router="off">
    @csrf

    <div class="settings-cards">
        <section class="settings-card">
            <h3 class="settings-card-title">۱. آزمون</h3>
            @if($tests->isEmpty())
                <p class="settings-card-text">هنوز آزمونی ساخته نشده است. ابتدا از مسیر دانش‌آموز یک آزمون بسازید.</p>
            @else
                <label class="settings-field">
                    <span class="settings-field-label">آزمون</span>
                    <select name="test_id" class="settings-input" required>
                        @foreach($tests as $test)
                            <option value="{{ $test->id }}" @selected(old('test_id') == $test->id)>
                                {{ $test->test_title }}{{ $test->lesson ? ' — '.$test->lesson : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('test_id')<span class="settings-error">{{ $message }}</span>@enderror
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">زمان برگزاری (اختیاری)</span>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="settings-input">
                </label>
            @endif
        </section>

        <section class="settings-card">
            <h3 class="settings-card-title">۲. دانش‌آموزان</h3>
            @include('consultant.bulk.partials._picker')
            @error('students')<span class="settings-error">{{ $message }}</span>@enderror
        </section>

        <div class="blog-form-actions">
            <button type="submit" class="primary-button" @disabled($tests->isEmpty())>
                <i class="fas fa-paper-plane" aria-hidden="true"></i> واگذاری گروهی
            </button>
        </div>
    </div>
</form>

@vite(['resources/js/features/bulk-actions.js'])
@endsection
