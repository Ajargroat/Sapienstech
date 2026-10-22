@php
    $tone = $tone ?? 'primary';
    $meta = $meta ?? null;
    $value = $value ?? null;
    $percent = $percent ?? null;
    $progress = $percent === null ? null : max(0, min(100, (int) $percent));
@endphp

<article class="dash-stat dash-stat--{{ $tone }}">
    <span class="dash-stat-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>
    <span class="dash-stat-value">
        @if($value === null || $value === '')
            —
        @else
            {{ persian_digits($value) }}
        @endif
    </span>
    <span class="dash-stat-label">{{ $label }}</span>
    @if($meta)
        <span class="dash-stat-meta">{{ $meta }}</span>
    @endif
    @if($progress !== null)
        <span class="dash-stat-track" aria-hidden="true">
            <span class="dash-stat-fill" style="--dash-value: {{ $progress }}%"></span>
        </span>
    @endif
</article>
