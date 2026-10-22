@extends('layouts.ops')

@section('title', $import->book_title)

@section('content')
    @php
        $tone = match ($import->status) {
            'ready' => 'ops-status--ok',
            'failed', 'needs_ocr' => 'ops-status--bad',
            'running' => 'ops-status--warn',
            default => '',
        };
    @endphp

    <section class="ops-card">
        <h2>{{ $import->book_title }}</h2>

        <ul class="ops-meta">
            <li>وضعیت: <span class="ops-status {{ $tone }}">{{ $statusLabels[$import->status] ?? $import->status }}</span></li>
            <li>ساختار: <b>{{ $structureLabels[$import->structure] ?? ($import->structure ?? '—') }}</b></li>
            <li>صفحه‌ها: <b>{{ persian_digits((int) $import->page_count) }}</b></li>
            <li>تصویرها: <b>{{ persian_digits($import->figures->count()) }}</b></li>
            <li>درس‌ها: <b>{{ persian_digits($import->lessons->count()) }}</b></li>
            <li>شروع: <b>{{ persian_digits($import->started_at?->format('Y/m/d H:i') ?? '—') }}</b></li>
            <li>پایان: <b>{{ persian_digits($import->finished_at?->format('Y/m/d H:i') ?? '—') }}</b></li>
        </ul>

        @if($import->error_text)
            <p class="ops-flash ops-flash--bad">
                {{ $import->error_text }}
                @if($import->error_code)
                    <code>{{ $import->error_code }}</code>
                @endif
            </p>
        @endif

        <div class="ops-actions">
            <form class="ops-inline-form" method="POST" action="{{ route('ops.lessons.extract', $import) }}">
                @csrf
                <button class="ops-button" type="submit">استخراج دوباره</button>
            </form>

            <a class="ops-button ops-button--ghost" href="{{ route('ops.lessons.index') }}">بازگشت به فهرست</a>
        </div>
    </section>

    @if($import->status === 'ready')
        <section class="ops-card">
            <h2>ساخت درس از یک بازهٔ صفحه</h2>
            <p class="ops-hint">متن صفحه‌ها به بلوک تبدیل می‌شود و یک درس پیش‌نویس ساخته می‌شود.</p>

            <form method="POST" action="{{ route('ops.lessons.build', $import) }}">
                @csrf

                <div class="ops-grid">
                    <label class="ops-field">
                        <span>نام درس</span>
                        <input class="ops-input" type="text" name="lesson_title" value="{{ old('lesson_title') }}" required>
                    </label>

                    <label class="ops-field">
                        <span>فصل (اختیاری)</span>
                        <input class="ops-input" type="text" name="chapter_title" value="{{ old('chapter_title') }}">
                    </label>

                    <label class="ops-field">
                        <span>از صفحه</span>
                        <input class="ops-input" type="number" name="page_from" min="1"
                               value="{{ old('page_from', 1) }}" required>
                    </label>

                    <label class="ops-field">
                        <span>تا صفحه</span>
                        <input class="ops-input" type="number" name="page_to" min="1"
                               value="{{ old('page_to', (int) $import->page_count) }}" required>
                    </label>

                    <div class="ops-field">
                        <button class="ops-button" type="submit">ساخت درس</button>
                    </div>
                </div>
            </form>
        </section>
    @endif

    <section class="ops-card">
        <h2>درس‌های این کتاب</h2>

        @if($import->lessons->isEmpty())
            <p class="ops-empty">هنوز درسی ساخته نشده است.</p>
        @else
            <table class="ops-table">
                <thead>
                <tr>
                    <th>درس</th>
                    <th>فصل</th>
                    <th>وضعیت</th>
                    <th>صفحه‌ها</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($import->lessons as $lesson)
                    <tr>
                        <td>{{ $lesson->lesson_title }}</td>
                        <td>{{ $lesson->chapter_title ?? '—' }}</td>
                        <td><span class="ops-status">{{ $lessonLabels[$lesson->status] ?? $lesson->status }}</span></td>
                        <td>
                            {{ persian_digits((int) $lesson->source_page_from) }}–{{ persian_digits((int) $lesson->source_page_to) }}
                        </td>
                        <td>
                            @include('ops._lesson-actions', ['lesson' => $lesson])
                            <a class="ops-button ops-button--ghost ops-button--sm"
                               href="{{ route('student.lesson-reader.show', $lesson) }}"
                               target="_blank" rel="noopener">پیش‌نمایش</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if($import->sourcePages->isNotEmpty())
        <section class="ops-card">
            <h2>صفحه‌های استخراج‌شده</h2>
            <p class="ops-hint">برای دیدن تصویر کامل صفحه، روی آن کلیک کنید.</p>

            <div class="ops-thumbs">
                @foreach($import->sourcePages->sortBy('page_number')->take(12) as $source)
                    <a class="ops-thumb"
                       href="{{ route('ops.lessons.page', ['import' => $import, 'page' => $source->page_number]) }}"
                       target="_blank" rel="noopener">
                        <img src="{{ route('ops.lessons.page', ['import' => $import, 'page' => $source->page_number]) }}"
                             alt="صفحهٔ {{ persian_digits((int) $source->page_number) }}" loading="lazy">
                        <span>صفحهٔ {{ persian_digits((int) $source->page_number) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
