{{-- resources/views/public/sections/advisor/quote-first.blade.php — statement leads, portrait is secondary --}}
@php
    $a      = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $tagColor = 'var(--c-' . ($a['tagline_color'] ?? 'secondary') . ')';
@endphp
<section id="{{ $a['id'] ?? 'advisor' }}" class="lp-section">
    <div class="landing-container flex flex-col gap-10 items-start" style="max-width:var(--measure)">

        @if (!empty($a['eyebrow']))
            <p class="lp-eyebrow">
                <i class="{{ $a['eyebrow']['icon'] }} text-sm" aria-hidden="true"></i>
                {{ $a['eyebrow']['label'] }}
            </p>
        @endif

        <blockquote class="{{ $reveal ? 'reveal' : '' }}">
            <p style="font-size:var(--h2-size);font-weight:var(--heading-weight);line-height:1.4;text-transform:var(--heading-transform);letter-spacing:var(--heading-letter-spacing)">
                {{ $a['tagline'] }}
            </p>
        </blockquote>

        <div class="flex items-center gap-4 {{ $reveal ? 'reveal' : '' }}">
            @if (!empty($a['image']))
                <img src="{{ tenant_asset($a['image']) }}" alt="{{ $a['image_alt'] ?? '' }}"
                     class="lp-avatar lp-avatar--lg {{ ($a['grayscale'] ?? false) ? 'grayscale' : '' }}">
            @endif
            <div>
                <strong class="block" style="font-size:var(--h3-size)">{{ $a['name'] }}</strong>
                @if (!empty($a['badge']['label']))
                    <span class="text-sm" style="color:{{ $tagColor }}">{{ $a['badge']['label'] }}</span>
                @endif
            </div>
        </div>

        <p class="leading-relaxed" style="color:var(--c-muted)">{{ $a['bio'] }}</p>

        @if (!empty($a['stats']))
            <div class="flex flex-wrap gap-x-10 gap-y-4 w-full">
                @foreach ($a['stats'] as $s)
                    <div>
                        <div class="text-2xl font-bold">{{ $s['value'] }}</div>
                        <div class="text-xs" style="color:var(--c-subtle)">{{ $s['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap gap-4 pt-2">
            @foreach ($a['buttons'] ?? [] as $b)
                @if ($b['visible'] ?? true)
                    @include('public.sections._button', ['label' => $b['label'], 'href' => $b['href'] ?? '#', 'tone' => $b['style'] ?? null])
                @endif
            @endforeach
        </div>
    </div>
</section>
