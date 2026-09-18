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
                <div class="settings-field">
                    @include('consultant.partials._filter_select', [
                        'name' => 'test_id',
                        'idPrefix' => 'bulk-test',
                        'label' => 'آزمون',
                        'options' => $tests->mapWithKeys(fn ($test) => [
                            (string) $test->id => $test->test_title.($test->lesson ? ' — '.$test->lesson : ''),
                        ])->all(),
                        'selected' => (string) old('test_id'),
                        'allowAll' => false,
                        'required' => true,
                        'placeholderText' => '— انتخاب آزمون —',
                    ])
                    @error('test_id')<span class="settings-error">{{ $message }}</span>@enderror
                </div>

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
