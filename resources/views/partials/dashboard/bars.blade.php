@php
    $items = collect($items ?? [])
        ->filter(fn ($item) => is_array($item) && !empty($item['label']))
        ->values();
@endphp

@if($items->isEmpty())
    <p class="dash-note">{{ $empty ?? 'داده‌ای برای نمایش نیست.' }}</p>
@else
    <div class="dash-bars">
        @foreach($items as $item)
            @php
                $count = (int) ($item['count'] ?? 0);
                $percent = max(0, min(100, (int) ($item['percent'] ?? 0)));
            @endphp
            <div class="dash-bar">
                <span class="dash-bar-label">{{ $item['label'] }}</span>
                <span class="dash-bar-value">{{ persian_digits($count) }} · {{ persian_digits($percent) }}٪</span>
                <span class="dash-bar-track" aria-hidden="true">
                    <span class="dash-bar-fill" style="--dash-value: {{ $percent }}%"></span>
                </span>
            </div>
        @endforeach
    </div>
@endif
