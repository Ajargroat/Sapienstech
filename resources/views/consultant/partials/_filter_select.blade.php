{{--
    Themed dropdown for the dashboard filter carousel.

    The browser's native <select> popup is chrome that cannot follow the tenant
    theme (its option list goes white-on-white in dark mode), so the popover
    uses a small custom listbox instead. Option rows borrow the report-card
    menu's .exam-filter-option look (label + count pill); $counts is a
    value => student-count map and $allCount is the unfiltered student total
    shown on the «همه» row.

    Two control kinds:
    - Single ($selected is a string): a JS-set hidden input ([data-filter-value])
      is the control; app.js re-checks it on pick, keeps the visible label and
      aria-selected in sync, and mirrors it into the other panels' hidden
      stacks before any filter form submits.
    - Multi ($selected is an array — StudentFilter::MULTI_FIELDS): every row is
      a real checkbox named "field[]" inside the owning form, so the browser
      serializes the picked values natively; the tray stays open while ticking
      (data-select-multi), the trigger shows how many are selected, and app.js
      rebuilds the other forms' hidden "field[]" mirrors on submit.

    A «همه» row is always prepended: it resets the field to "no filter", which
    StudentFilter treats as unset (empty string / empty array).
--}}
@php
    $allLabel = $labels['filter_all'] ?? 'همه';
    $idBase = $idPrefix ?? $name;
    $listId = 'filter-list-'.$idBase;
    $multi = is_array($selected);
    $selectedValues = $multi ? array_map('strval', array_values($selected)) : [];
    $single = $multi ? '' : (string) ($selected ?? '');
    $controlName = $multi ? $name.'[]' : $name;
    $placeholder = $multi ? $selectedValues === [] : $single === '';
    $counts = $counts ?? null;
    $allCount = $allCount ?? null;
    $withCounts = $counts !== null;
    $total = $allCount ?? ($counts['total'] ?? null);
    // $allowAll (default true) prepends the «همه» reset row the dashboard
    // filter fields need; the required bulk-creation pickers (test/day) turn
    // it off and instead show $placeholderText until something is picked.
    // $required marks the value-holder so app.js can block an empty submit
    // (a hidden input is skipped by native constraint validation).
    $allowAll = $allowAll ?? true;
    $placeholderText = $placeholderText ?? ($labels['filter_select_placeholder'] ?? '— انتخاب کنید —');
    $required = $required ?? false;
@endphp
<div class="filter-select" data-filter-select
    @if($multi) data-select-multi data-filter-multi data-count-unit="{{ $labels['filter_selected'] ?? 'مورد' }}" data-all-label="{{ $allLabel }}" @endif>
    <span class="filter-field-name" id="filter-label-{{ $idBase }}">{{ $label }}</span>
    <button
        type="button"
        class="filter-select-trigger @if($placeholder) is-placeholder @endif"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $listId }}"
        aria-labelledby="filter-label-{{ $idBase }}"
        data-filter-select-trigger
    >
        <span class="filter-select-value" data-filter-select-display>
            @if($multi)
                @if($selectedValues === [])
                    {{ $allLabel }}
                @else
                    {{ persian_digits(count($selectedValues)) }} {{ $labels['filter_selected'] ?? 'مورد' }}
                @endif
            @else
                {{ $single === '' ? ($allowAll ? $allLabel : $placeholderText) : ($options[$single] ?? $single) }}
            @endif
        </span>
        <i class="fas fa-chevron-down filter-select-caret" aria-hidden="true"></i>
    </button>

    <div class="filter-select-list" id="{{ $listId }}" role="{{ $multi ? 'group' : 'listbox' }}" tabindex="-1"
         aria-labelledby="filter-label-{{ $idBase }}" data-filter-select-list>
        @if($multi)
            {{-- Clear-all row: app.js unticks every box when picked. --}}
            <button
                type="button"
                class="filter-select-option exam-filter-option @if($placeholder) is-active @endif"
                aria-selected="{{ $placeholder ? 'true' : 'false' }}"
                data-value=""
                data-label="{{ $allLabel }}"
                data-filter-clear
            >
                <span class="filter-select-option-text">{{ $allLabel }}</span>
                @if($withCounts && $total !== null)<span class="filter-select-count">{{ persian_digits($total) }}</span>@endif
            </button>
            @foreach($options as $value => $option)
                @php($checked = in_array((string) $value, $selectedValues, true))
                <label class="filter-select-option exam-filter-option" data-filter-select-option data-label="{{ $option }}">
                    <input type="checkbox" name="{{ $controlName }}" value="{{ $value }}" @checked($checked)>
                    <i class="fas fa-check filter-select-option-mark" aria-hidden="true"></i>
                    <span class="filter-select-option-text">{{ $option }}</span>
                    @if($withCounts && isset($counts[$value]))<span class="filter-select-count">{{ persian_digits($counts[$value]) }}</span>@endif
                </label>
            @endforeach
        @else
            @if($allowAll)
                <button
                    type="button"
                    role="option"
                    class="filter-select-option exam-filter-option @if($single === '') is-active @endif"
                    aria-selected="{{ $single === '' ? 'true' : 'false' }}"
                    data-value=""
                    data-label="{{ $allLabel }}"
                >
                    <span class="filter-select-option-text">{{ $allLabel }}</span>
                    @if($withCounts && $total !== null)<span class="filter-select-count">{{ persian_digits($total) }}</span>@endif
                </button>
            @endif
            @foreach($options as $value => $option)
                @php($active = $single !== '' && (string) $value === $single)
                <button
                    type="button"
                    role="option"
                    class="filter-select-option exam-filter-option @if($active) is-active @endif"
                    aria-selected="{{ $active ? 'true' : 'false' }}"
                    data-value="{{ $value }}"
                    data-label="{{ $option }}"
                >
                    <span class="filter-select-option-text">{{ $option }}</span>
                    @if($withCounts && isset($counts[$value]))<span class="filter-select-count">{{ persian_digits($counts[$value]) }}</span>@endif
                </button>
            @endforeach
        @endif
    </div>

    @unless($multi)
        {{-- Echo the attr: a directive glued to attribute text (value@if) is
             not compiled — Blade's \B@ regex needs a non-word char before @. --}}
        <input type="hidden" name="{{ $name }}" value="{{ $single }}" data-filter-value{{ $required ? ' required' : '' }}>
    @endunless
</div>
