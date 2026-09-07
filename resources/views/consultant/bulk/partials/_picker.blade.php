{{--
    Shared student picker for the bulk flows.

    Renders inside the main POST form, so filtering is done with links
    rather than a nested form. The data-router-region areas are swapped in
    place by the page router (the create page is fetched, only the picker
    updates), so half-filled exam/schedule fields survive a filter change.
    The current filter set travels back as hidden inputs: when select_all is
    chosen the server re-applies them (App\Support\BulkSelection) instead of
    trusting the visible checkboxes.
--}}
<div class="bulk-picker" data-bulk-picker>
    <div class="bulk-picker-head" data-router-region="picker">
        <h4 class="bulk-picker-title">دانش‌آموزان <span class="bulk-picker-count" data-picker-count>{{ persian_digits($students->count()) }}</span></h4>

        <div class="bulk-picker-modes">
            <label class="bulk-mode">
                <input type="radio" name="select_all" value="0" data-mode-explicit @checked(!old('select_all'))>
                <span>انتخاب دستی</span>
            </label>
            <label class="bulk-mode">
                <input type="radio" name="select_all" value="1" data-mode-all @checked(old('select_all'))>
                <span>همهٔ نتایج فیلتر ({{ persian_digits($students->count()) }})</span>
            </label>
        </div>
    </div>

    {{-- Current filters, posted back for server-side select_all --}}
    <div hidden data-router-region="picker">
        <input type="hidden" name="search" value="{{ $filters['search'] }}">
        <input type="hidden" name="grade" value="{{ $filters['grade'] }}">
        <input type="hidden" name="gender" value="{{ $filters['gender'] }}">
        <input type="hidden" name="major" value="{{ $filters['major'] }}">
    </div>

    <div class="bulk-filterbar" data-router-region="picker">
        <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}"
           class="blog-chip {{ $filters['search'] === '' ? 'is-active' : '' }}">بدون جستجو</a>

        @foreach(['grade' => $gradeOptions, 'gender' => $genderOptions, 'major' => $majorOptions] as $field => $options)
            @foreach($options as $option)
                <a href="{{ request()->fullUrlWithQuery([$field => $option]) }}"
                   class="blog-chip {{ $filters[$field] === $option ? 'is-active' : '' }}">
                    {{ $option }}
                </a>
            @endforeach
        @endforeach
    </div>

    <label class="bulk-select-toggle">
        <input type="checkbox" data-check-all>
        <span>انتخاب همهٔ مورد‌های نمایش‌داده‌شده</span>
    </label>

    <div class="bulk-student-list" data-student-list data-router-region="picker">
        @foreach($students as $student)
            <label class="bulk-student-row">
                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" data-student-check
                       @checked(is_array(old('student_ids')) && in_array($student->id, old('student_ids')))>
                <span class="bulk-student-name">{{ $student->name }}</span>
                <span class="bulk-student-tags">
                    @if($student->grade)<span class="student-tag">{{ $student->grade }}</span>@endif
                    @if($student->major)<span class="student-tag">{{ $student->major }}</span>@endif
                </span>
            </label>
        @endforeach
        @if($students->isEmpty())
            <p class="bulk-picker-empty">دانش‌آموزی با این فیلترها پیدا نشد.</p>
        @endif
    </div>
</div>
