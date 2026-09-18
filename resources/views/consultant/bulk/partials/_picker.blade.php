{{--
    Shared student picker for the bulk flows.

    Renders inside the main POST form, so filtering is done with links
    rather than a nested form (the filterbar's themed dropdowns hold those
    links). The data-router-region areas are swapped in place by the page
    router (the create page is fetched, only the picker updates), so
    half-filled exam/schedule fields survive a filter change.
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

    {{-- Current filters, posted back for server-side select_all. Multi-value
         fields (StudentFilter::MULTI_FIELDS) carry one hidden input per
         selected value. --}}
    <div hidden data-router-region="picker">
        @foreach($filters as $filterKey => $filterValue)
            @if(is_array($filterValue))
                @foreach($filterValue as $filterItem)
                    <input type="hidden" name="{{ $filterKey }}[]" value="{{ $filterItem }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
            @endif
        @endforeach
    </div>

    {{-- One themed dropdown per filter field instead of a wall of chips.
         These stay links (the picker renders inside the main POST form, so
         a nested form is impossible); the shared dropdown JS folds the
         panel and mirrors the trigger label while the router swaps rows.
         Each option link toggles its value in the field's selected set, so
         the picker's filters are multi-value exactly like the dashboard's. --}}
    <div class="bulk-filterbar" data-router-region="picker">
        @foreach([
            'grade' => ['label' => 'پایه تحصیلی', 'options' => $gradeOptions],
            'gender' => ['label' => 'جنسیت', 'options' => $genderOptions],
            'major' => ['label' => 'رشته تحصیلی', 'options' => $majorOptions],
        ] as $field => $meta)
            @php($current = array_map('strval', array_values((array) ($filters[$field] ?? []))))
            <div class="filter-select" data-filter-select data-select-auto-width>
                <button type="button" class="filter-select-trigger @if($current === []) is-placeholder @endif"
                        data-filter-select-trigger aria-haspopup="true" aria-expanded="false">
                    <span class="filter-select-value" data-filter-select-display>
                        @if($current === [])
                            {{ $meta['label'] }}
                        @else
                            {{ implode('، ', $current) }}
                        @endif
                    </span>
                    <i class="fas fa-chevron-down filter-select-caret" aria-hidden="true"></i>
                </button>
                <div class="filter-select-list" data-filter-select-list aria-label="{{ $meta['label'] }}">
                    <a href="{{ request()->fullUrlWithQuery([$field => null]) }}"
                       class="filter-select-option @if($current === []) is-active @endif"
                       data-filter-select-option data-placeholder data-label="{{ $meta['label'] }}">همه</a>
                    @foreach($meta['options'] as $option)
                        {{-- Expression forms only (see StudentFilter::MULTI_FIELDS): a
                             raw-php block marker in this file would swallow the
                             @php(...) expressions above it during compilation. --}}
                        @php($option = (string) $option)
                        @php($toggled = in_array($option, $current, true) ? array_values(array_diff($current, [$option])) : array_merge($current, [$option]))
                        <a href="{{ request()->fullUrlWithQuery([$field => $toggled === [] ? null : $toggled]) }}"
                           class="filter-select-option @if(in_array($option, $current, true)) is-active @endif"
                           data-filter-select-option data-label="{{ $option }}">{{ $option }}</a>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($filters['search'] !== '')
            <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="blog-chip">
                <i class="fas fa-times" aria-hidden="true"></i>
                بدون جستجو «{{ $filters['search'] }}»
            </a>
        @endif
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
