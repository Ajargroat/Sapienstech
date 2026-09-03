{{-- resources/views/public/sections/cta.blade.php --}}
@php($cta = $L['cta'])
@php($reveal = $anim['reveal'] ?? true)
<section class="py-32 px-[var(--container-padding)] relative overflow-hidden">
    <div class="absolute inset-0 bg-linear-to-b from-transparent to-(--c-surface-alt) pointer-events-none z-0"></div>
    <div class="absolute bottom-0 left-1/2 -translate-x-1/2 w-[800px] h-[300px] blur-[150px] pointer-events-none z-0"
         style="background:color-mix(in srgb, var(--c-primary) 5%, transparent)"></div>

    <div class="max-w-4xl mx-auto text-center relative z-10 {{ $reveal ? 'reveal fade-bottom' : '' }}">
        <h2 class="text-4xl md:text-6xl font-bold mb-6 tracking-tight">{{ $cta['heading'] }}</h2>
        <p class="text-xl mb-10 max-w-2xl mx-auto" style="color:var(--c-muted)">{{ $cta['text'] }}</p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            @foreach ($cta['buttons'] as $b)
                @if ($b['visible'] ?? true)
                    <a href="{{ route($b['route']) }}"
                       class="w-full sm:w-auto px-10 py-4 rounded-[var(--radius-button)] font-semibold text-lg transition-colors"
                       style="{{ $b['style'] === 'solid'
                           ? 'background:var(--c-text);color:var(--c-background)'
                           : 'background:var(--c-glass);border:1px solid var(--c-glass-border);color:var(--c-text)' }}">
                        {{ $b['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</section>
