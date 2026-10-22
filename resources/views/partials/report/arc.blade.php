{{--
    Report-card gauge — a 270° arc for the newest score.

    Different on purpose from partials/dashboard/ring.blade.php: the ring is a
    "how much is done" donut; this one is a SCALE, so it also marks where the
    pass line sits. Both are pure SVG with token colours, no chart library.

    @param float $value    0..100
    @param float $passLine 0..100, the tenant's own line (Settings → کارنامه)
--}}
@php
    $value    = max(0, min(100, (float) ($value ?? 0)));
    $passLine = max(0, min(100, (float) ($passLine ?? 50)));

    // Geometry: 270° sweep starting at the lower-left (135°), radius 42.
    $cx = 60; $cy = 60; $r = 42; $sweep = 270;
    $circumference = 2 * pi() * $r;
    $arc = $circumference * ($sweep / 360);
    $start = 135;

    $angle = fn (float $p) => ($start + ($sweep * $p / 100));
    $mark = function (float $p) use ($cx, $cy, $r, $angle, $sweep): array {
        $a = deg2rad($angle($p));
        $inner = $r - 7;
        $outer = $r + 5;
        return [
            round($cx + $outer * cos($a), 2),
            round($cy + $outer * sin($a), 2),
            round($cx + $inner * cos($a), 2),
            round($cy + $inner * sin($a), 2),
        ];
    };
    [$px1, $py1, $px2, $py2] = $mark($passLine);
@endphp
<svg class="rc-arc" viewBox="0 0 120 120" role="img"
     aria-label="{{ persian_digits($value) }} درصد — خط قبولی {{ persian_digits($passLine) }} درصد">
    <circle class="rc-arc-track" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
            stroke-dasharray="{{ round($arc, 2) }} {{ round($circumference, 2) }}"
            transform="rotate({{ $start }} {{ $cx }} {{ $cy }})"></circle>
    <circle class="rc-arc-value @if($value < $passLine) is-below @else is-above @endif"
            cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
            stroke-dasharray="{{ round($arc * $value / 100, 2) }} {{ round($circumference, 2) }}"
            transform="rotate({{ $start }} {{ $cx }} {{ $cy }})"></circle>
    <line class="rc-arc-pass" x1="{{ $px1 }}" y1="{{ $py1 }}" x2="{{ $px2 }}" y2="{{ $py2 }}"></line>
    <text class="rc-arc-number" x="{{ $cx }}" y="{{ $cy - 2 }}" text-anchor="middle">
        {{ persian_digits(rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.')) }}٪
    </text>
    <text class="rc-arc-caption" x="{{ $cx }}" y="{{ $cy + 14 }}" text-anchor="middle">آخرین درصد</text>
</svg>
