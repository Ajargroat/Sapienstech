{{--
    Radio chip group for the dashboard filter carousel.

    $options is a value => label map; the "همه" chip carries the empty value,
    matching StudentFilter::fromRequest's whitelist semantics.
--}}
<div class="filter-field">
    <span class="filter-field-name">{{ $label }}</span>
    <div class="filter-chips">
        <input
            type="radio"
            name="{{ $name }}"
            value=""
            id="{{ $idPrefix }}-all"
            class="filter-chip-input"
            @checked($selected === '')
        >
        <label for="{{ $idPrefix }}-all" class="filter-chip">{{ $labels['filter_all'] }}</label>
        @foreach($options as $value => $option)
            <input
                type="radio"
                name="{{ $name }}"
                value="{{ $value }}"
                id="{{ $idPrefix }}-{{ $loop->index }}"
                class="filter-chip-input"
                @checked($selected !== '' && $selected === (string) $value)
            >
            <label for="{{ $idPrefix }}-{{ $loop->index }}" class="filter-chip">{{ $option }}</label>
        @endforeach
    </div>
</div>
