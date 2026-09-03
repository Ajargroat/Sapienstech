{{-- resources/views/public/sections/services.blade.php --}}
@php
    $s      = $L['services'];
    $reveal = $anim['reveal'] ?? true;
    $stagger = (int) $theme['landing']['stagger_ms'];
@endphp
<section id="{{ $s['id'] }}" class="px-[var(--container-padding)]" style="padding-top:var(--section-gap);padding-bottom:var(--section-gap)">
    <div class="landing-container">
        <div class="text-center mb-16 {{ $reveal ? 'reveal fade-bottom' : '' }}">
            <h2 class="mb-4" style="font-size:var(--h2-size);font-weight:var(--heading-weight)">{{ $s['heading'] }}</h2>
            <p class="max-w-2xl mx-auto" style="color:var(--c-muted)">{{ $s['subheading'] }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-6"
             style="--cols:{{ $s['columns'] }}">
            @foreach ($s['items'] as $i => $item)
                @php($accent = 'var(--c-' . ($item['accent'] ?? 'primary') . ')')
                <div class="group reveal fade-bottom transition-all duration-300 hover:-translate-y-2 p-[var(--card-padding)] rounded-[var(--radius-card)]"
                     style="background:var(--c-surface);border:1px solid var(--c-border);{{ $reveal ? 'transition-delay:' . ($i % $s['columns']) * $stagger . 'ms;' : '' }}">
                    <div class="w-12 h-12 rounded-[var(--radius-md)] flex items-center justify-center mb-6 transition-transform group-hover:scale-110"
                         style="background:color-mix(in srgb, {{ $accent }} 10%, transparent);color:{{ $accent }}">
                        <i class="{{ $item['icon'] }} text-xl"></i>
                    </div>
                    <h3 class="font-semibold mb-3" style="font-size:var(--h3-size)">{{ $item['title'] }}</h3>
                    <p class="text-sm leading-relaxed" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
