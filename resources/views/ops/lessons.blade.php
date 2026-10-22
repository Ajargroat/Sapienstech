@extends('layouts.ops')

@section('title', 'کتاب‌ها و استخراج')

@section('content')
    <section class="ops-card">
        <h2>بارگذاری کتاب</h2>
        <p class="ops-hint">
            فایل PDF را اینجا می‌گذاریم، متن همان لحظه استخراج می‌شود و تصویرها جدا می‌شوند.
            این مرحله کار ماست و در هیچ پنلی از مستأجرها دیده نمی‌شود.
        </p>

        <form method="POST" action="{{ route('ops.lessons.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="ops-grid">
                <label class="ops-field">
                    <span>نام کتاب</span>
                    <input class="ops-input" type="text" name="book_title" value="{{ old('book_title') }}" required>
                </label>

                <label class="ops-field">
                    <span>پایه (اختیاری)</span>
                    <input class="ops-input" type="text" name="grade" value="{{ old('grade') }}"
                           placeholder="مثلاً هفتم">
                </label>

                <label class="ops-field">
                    <span>فایل PDF (حداکثر {{ $maxKb }} کیلوبایت)</span>
                    <input class="ops-input" type="file" name="file" accept="application/pdf" required>
                </label>

                <div class="ops-field">
                    <button class="ops-button" type="submit">بارگذاری و استخراج</button>
                </div>
            </div>
        </form>
    </section>

    <section class="ops-card">
        <h2>کتاب‌های بارگذاری‌شده</h2>

        @if($imports->isEmpty())
            <p class="ops-empty">هنوز کتابی بارگذاری نشده است.</p>
        @else
            <table class="ops-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>کتاب</th>
                    <th>وضعیت</th>
                    <th>صفحه</th>
                    <th>تصویر</th>
                    <th>درس</th>
                    <th>زمان</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($imports as $import)
                    @php
                        $tone = match ($import->status) {
                            'ready' => 'ops-status--ok',
                            'failed', 'needs_ocr' => 'ops-status--bad',
                            'running' => 'ops-status--warn',
                            default => '',
                        };
                    @endphp
                    <tr>
                        <td>{{ persian_digits($import->id) }}</td>
                        <td>{{ $import->book_title }}</td>
                        <td><span class="ops-status {{ $tone }}">{{ $statusLabels[$import->status] ?? $import->status }}</span></td>
                        <td>{{ persian_digits((int) $import->page_count) }}</td>
                        <td>{{ persian_digits((int) $import->figures_count) }}</td>
                        <td>{{ persian_digits((int) $import->lessons_count) }}</td>
                        <td>{{ persian_digits($import->created_at?->format('Y/m/d H:i') ?? '') }}</td>
                        <td>
                            <div class="ops-actions">
                                <a class="ops-button ops-button--ghost ops-button--sm"
                                   href="{{ route('ops.lessons.show', $import) }}">باز کردن</a>

                                @if($import->status !== 'ready')
                                    <form class="ops-inline-form" method="POST"
                                          action="{{ route('ops.lessons.extract', $import) }}">
                                        @csrf
                                        <button class="ops-button ops-button--sm" type="submit">استخراج</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="ops-card">
        <h2>درس‌های ساخته‌شده</h2>

        @if($lessons->isEmpty())
            <p class="ops-empty">هنوز درسی از این کتاب‌ها ساخته نشده است.</p>
        @else
            <table class="ops-table">
                <thead>
                <tr>
                    <th>درس</th>
                    <th>کتاب</th>
                    <th>وضعیت</th>
                    <th>صفحه‌ها</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($lessons as $lesson)
                    <tr>
                        <td>{{ $lesson->lesson_title }}</td>
                        <td>{{ $lesson->book_title }}</td>
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
@endsection
