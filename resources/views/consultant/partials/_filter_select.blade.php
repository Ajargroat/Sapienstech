{{--
    Themed dropdown for the dashboard filter carousel.

    The browser's native <select> popup is chrome that cannot follow the tenant
    theme (its option list goes white-on-white in dark mode), so the popover
    uses a small custom listbox instead. A hidden input carries the submitted
    value; app.js re-checks that input whenever the user picks an option, keeps
    the visible label and aria-selected state in sync, and mirrors it into the
    other panels' hidden stacks before any filter form submits.

    $options is a value => label map. An empty-value «همه» entry is prepended so
    "no filter selected" is an explicit, reachable option (StudentFilter's
    whitelist treats an empty string as unset).
--}}
@php
    $allLabel = $labels['filter_all'] ?? 'همه';
    $displayLabel = ($selected === '' || $selected === null)
        ? $allLabel
        : ($options[$selected] ?? (string) $selected);
    $listId = 'filter-list-'.($idPrefix ?? $name);
@endphp
<div class="filter-select" data-filter-select>
    <span class="filter-field-name" id="filter-label-{{ $idPrefix ?? $name }}">{{ $label }}</span>
    <button
        type="button"
        class="filter-select-trigger @if($selected === '' || $selected === null) is-placeholder @endif"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $listId }}"
        aria-labelledby="filter-label-{{ $idPrefix ?? $name }}"
        data-filter-select-trigger
    >
        <span class="filter-select-value" data-filter-select-display>{{ $displayLabel }}</span>
        <i class="fas fa-chevron-down filter-select-caret" aria-hidden="true"></i>
    </button>
    <div class="filter-select-list" id="{{ $listId }}" role="listbox" tabindex="-1" aria-labelledby="filter-label-{{ $idPrefix ?? $name }}" data-filter-select-list>
        <button
            type="button"
            role="option"
            class="filter-select-option is-active"
            aria-selected="true"
            data-value=""
            data-label="{{ $allLabel }}"
        >{{ $allLabel }}</button>
        @foreach($options as $value => $option)
            @php($active = $selected !== '' && $selected !== null && (string) $selected === (string) $value)
            <button
                type="button"
                role="option"
                class="filter-select-option @if($active) is-active @endif"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                data-value="{{ $value }}"
                data-label="{{ $option }}"
            >{{ $option }}</button>
        @endforeach
    </div>
    <input type="hidden" name="{{ $name }}" value="{{ $selected ?? '' }}" data-filter-value>
</div>
