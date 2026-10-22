@php
    $value = max(0, min(100, (int) ($value ?? 0)));
    $radius = 30;
    $circumference = 2 * pi() * $radius;
    $offset = $circumference * (1 - ($value / 100));
@endphp

<div class="dash-pulse">
    <div class="dash-ring">
        <svg viewBox="0 0 72 72" aria-hidden="true" focusable="false">
            <circle class="dash-ring-track" cx="36" cy="36" r="{{ $radius }}"></circle>
            <circle
                class="dash-ring-value"
                cx="36"
                cy="36"
                r="{{ $radius }}"
                stroke-dasharray="{{ round($circumference, 2) }}"
                stroke-dashoffset="{{ round($offset, 2) }}"
            ></circle>
        </svg>
        <span class="dash-ring-number">{{ persian_digits($value) }}<small>٪</small></span>
    </div>
    <div class="dash-pulse-copy">
        <span class="dash-pulse-label">{{ $label }}</span>
        <span class="dash-pulse-caption">{{ $caption }}</span>
    </div>
</div>
