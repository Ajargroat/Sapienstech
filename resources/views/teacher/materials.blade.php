{{--
    Teacher panel — lesson materials: upload form (PDF first-class) into the
    tenant's own asset tree, and the library grouped by subject with
    download counters and safe deletes.
--}}
@extends('layouts.teacher')

@section('content')
<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-cloud-arrow-up"></i> بارگذاری جزوه یا منبع آموزشی</h2>
    </header>

    @include('consultant.blog.flash')

    <form
        method="POST"
        action="{{ route('teacher.materials.store') }}"
        enctype="multipart/form-data"
        class="teacher-form"
    >
        @csrf

        <div class="teacher-form-grid">
            <label class="settings-field">
                <span class="settings-field-label">عنوان</span>
                <input
                    type="text"
                    name="title"
                    value="{{ old('title') }}"
                    class="settings-input @error('title') is-invalid @enderror"
                    required
                >
                @error('title')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">درس</span>
                <input
                    type="text"
                    name="subject"
                    value="{{ old('subject') }}"
                    list="teacher-subjects"
                    class="settings-input @error('subject') is-invalid @enderror"
                >
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
                <span class="settings-field-label">فایل (حداکثر {{ persian_digits((int) round($uploadRules['max_kb'] / 1024)) }} مگابایت)</span>
                <label class="file-picker">
                    <i class="fas fa-file-pdf" aria-hidden="true"></i>
                    <input type="file" name="file" accept=".{{ implode(',.', $uploadRules['types']) }}" required>
                </label>
                @error('file')<span class="settings-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <label class="settings-field">
            <span class="settings-field-label">توضیح (اختیاری)</span>
            <textarea name="description" rows="3" class="settings-input @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
            @error('description')<span class="settings-error">{{ $message }}</span>@enderror
        </label>

        <div class="teacher-form-actions">
            <label class="teacher-switch">
                <input type="hidden" name="is_published" value="0">
                <input type="checkbox" name="is_published" value="1" checked>
                <span>انتشار برای دانش‌آموزان</span>
            </label>
            <button type="submit" class="primary-button"><i class="fas fa-upload"></i> بارگذاری</button>
        </div>
    </form>
</div>

<div class="panel student-panel">
    <header class="student-panel-head">
        <h2><i class="fas fa-book-open-reader"></i> کتابخانه جزوه‌ها</h2>
        @if($materials->isNotEmpty())
            <span class="count-badge">
                {{ persian_digits($materials->count()) }} فایل · {{ persian_digits($totalDownloads) }} دانلود
            </span>
        @endif
    </header>

    @if($materials->isNotEmpty())
        @foreach($bySubject as $subject => $group)
            <h3 class="teacher-subject-head"><i class="fas fa-book"></i> {{ $subject }}</h3>
            <div class="teacher-grid" data-stagger>
                @foreach($group as $material)
                    <article class="exam-card teacher-material-card">
                        <div class="exam-card-icon exam-card-icon--quiz">
                            @if(! $material->is_published)
                                <span class="exam-status exam-status--missed">پیش‌نویس</span>
                            @endif
                            <i class="fas fa-file-pdf teacher-material-icon"></i>
                            <span class="exam-card-icon-label">{{ $material->formattedSize() }}</span>
                        </div>
                        <div class="exam-card-body">
                            <h3 class="exam-card-title">{{ $material->title }}</h3>
                            <ul class="exam-card-facts">
                                @if($material->grade)
                                    <li><i class="fas fa-layer-group"></i>{{ $material->grade }}</li>
                                @else
                                    <li><i class="fas fa-layer-group"></i>همه پایه‌ها</li>
                                @endif
                                <li><i class="fas fa-download"></i>{{ persian_digits($material->download_count) }} دانلود</li>
                                <li>
                                    <i class="far fa-clock"></i>
                                    <time class="fa-date" datetime="{{ $material->created_at->format('Y-m-d\TH:i') }}Z">{{ persian_digits($material->created_at->format('Y/m/d')) }}</time>
                                </li>
                            </ul>
                            @if($material->description)
                                <p class="teacher-material-desc">{{ $material->description }}</p>
                            @endif
                            <div class="teacher-material-actions">
                                <a href="{{ route('teacher.materials.download', $material) }}" class="primary-button">
                                    <i class="fas fa-download"></i> دانلود
                                </a>
                                <form method="POST" action="{{ route('teacher.materials.destroy', $material) }}" data-confirm="این جزوه برای همیشه حذف شود؟">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="danger-button"><i class="fas fa-trash"></i> حذف</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <i class="far fa-folder-open"></i>
            <h3>هنوز جزوه‌ای بارگذاری نشده</h3>
            <p>اولین فایل آموزشی خود را از فرم بالا بارگذاری کنید.</p>
        </div>
    @endif
</div>

@vite(['resources/js/features/teacher-panel.js'])
@endsection
