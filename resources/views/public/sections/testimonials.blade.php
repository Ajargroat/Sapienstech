{{-- resources/views/public/sections/testimonials.blade.php --}}
@php
    $tm     = $L['testimonials'];
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $tm['id'] }}" class="px-[var(--container-padding)] overflow-hidden" style="padding-top:var(--section-gap);padding-bottom:var(--section-gap)">
    <div class="landing-container">
        <div class="text-center mb-16 {{ $reveal ? 'reveal fade-bottom' : '' }}">
            <h2 class="mb-4" style="font-size:var(--h2-size);font-weight:var(--heading-weight)">{{ $tm['heading'] }}</h2>
            <p class="max-w-2xl mx-auto" style="color:var(--c-muted)">{{ $tm['subheading'] }}</p>
        </div>

        <div class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-8 hide-scrollbar cursor-grab active:cursor-grabbing md:grid md:overflow-visible"
             style="grid-template-columns:repeat({{ count(array_filter($tm['items'], fn ($i) => $i['visible'] ?? true)) }}, minmax(0, 1fr))">
            @foreach ($tm['items'] as $i => $item)
                @continue(! ($item['visible'] ?? true))
                <div class="min-w-[300px] w-full snap-center rounded-[var(--radius-card)] p-8 {{ $reveal ? 'reveal fade-bottom' : '' }}"
                     style="background:var(--c-surface);border:1px solid var(--c-border);transition-delay:{{ $i * (int) $theme['landing']['stagger_ms'] }}ms">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center text-lg font-bold"
                             style="background:linear-gradient(45deg, var(--c-{{ $item['from'] }}), var(--c-{{ $item['to'] }}));color:var(--c-on-primary)">
                            {{ $item['initials'] }}
                        </div>
                        <div>
                            <h4 class="font-bold">{{ $item['name'] }}</h4>
                            <p class="text-sm font-medium" style="color:var(--c-primary)">{{ $item['result'] }}</p>
                        </div>
                    </div>
                    <p class="leading-relaxed text-sm" style="color:var(--c-muted)">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
