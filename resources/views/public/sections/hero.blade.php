{{-- resources/views/public/sections/hero.blade.php --}}
@php
    $h         = $L['hero'];
    $textFirst = ($h['text_side'] ?? 'start') === 'start';
    $reveal    = $anim['reveal'] ?? true;
    $float     = $anim['float'] ?? true;
@endphp
<section class="relative flex items-center overflow-hidden px-[var(--container-padding)]"
         style="min-height:var(--hero-min-height);padding-top:calc(var(--nav-height) + 2rem);padding-bottom:3rem">

    <div class="absolute rounded-full blur-[var(--glow-blur)] pointer-events-none"
         style="top:25%;inset-inline-start:25%;width:var(--glow-size);height:var(--glow-size);background:var(--c-primary);opacity:var(--glow-opacity)"></div>
    <div class="absolute rounded-full blur-[var(--glow-blur)] pointer-events-none"
         style="bottom:25%;inset-inline-end:25%;width:var(--glow-size);height:var(--glow-size);background:var(--c-secondary);opacity:var(--glow-opacity)"></div>

    <div class="landing-container w-full grid md:grid-cols-2 gap-[var(--grid-gap)] items-center relative z-10">

        {{-- Text column --}}
        <div class="flex flex-col gap-6 {{ $reveal ? 'reveal fade-bottom' : '' }}"
             style="text-align:{{ $h['text_align'] }};{{ $textFirst ? '' : 'order:2' }}">
            <h1 style="font-size:var(--h1-size);font-weight:var(--heading-weight);line-height:var(--hero-line-height);letter-spacing:-.02em">
                {{ $h['title_line1'] }} <br>
                @if ($h['gradient_text'])
                    {{-- Inline (not a utility class) because the direction comes
                         from config and Tailwind can't see runtime-built names. --}}
                    <span class="text-transparent bg-clip-text"
                          style="background-image:linear-gradient({{ $h['gradient_dir'] === 'to-r' ? 'to right' : 'to left' }}, var(--c-primary), var(--c-secondary))">
                        {{ $h['title_line2'] }}
                    </span>
                @else
                    <span style="color:var(--c-primary)">{{ $h['title_line2'] }}</span>
                @endif
            </h1>
            <p class="text-lg max-w-lg leading-relaxed" style="color:var(--c-muted)">{{ $h['subtitle'] }}</p>
            <div class="flex flex-wrap gap-4 mt-4">
                @foreach ($h['buttons'] as $b)
                    @if ($b['visible'] ?? true)
                        <a href="{{ $b['href'] }}"
                           class="px-8 py-3.5 rounded-[var(--radius-button)] font-semibold transition-colors"
                           style="{{ $b['style'] === 'solid'
                               ? 'background:var(--c-text);color:var(--c-background)'
                               : 'background:var(--c-glass);border:1px solid var(--c-glass-border);color:var(--c-text)' }}">
                            {{ $b['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Floating dashboard mockup --}}
        @if ($h['mockup'])
            <div class="relative h-[500px] w-full {{ $reveal ? 'reveal fade-bottom' : '' }} hidden md:block"
                 style="{{ $textFirst ? 'order:2' : 'order:1' }}">

                {{-- Main chart card --}}
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-96 backdrop-blur-xl rounded-[var(--radius-lg)] p-6 shadow-2xl z-20 flex flex-col gap-4 {{ $float ? 'animate-float' : '' }}"
                     style="background:color-mix(in srgb, var(--c-surface-alt) 80%, transparent);border:1px solid var(--c-glass-border)">
                    <div class="flex justify-between items-center pb-4" style="border-bottom:1px solid var(--c-border)">
                        <div class="flex gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500/80"></div>
                        </div>
                        <div class="w-20 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
                    </div>
                    <div class="w-full h-24 rounded-[var(--radius-md)] flex items-end p-2 gap-1 bg-linear-to-r from-(--c-primary)/20 to-(--c-secondary)/20"
                         style="border:1px solid var(--c-border)">
                        <div class="w-full rounded-t-sm h-[40%] bg-(--c-primary)/80"></div>
                        <div class="w-full rounded-t-sm h-[60%] bg-(--c-primary)/80"></div>
                        <div class="w-full rounded-t-sm h-[30%] bg-(--c-primary)/80"></div>
                        <div class="w-full rounded-t-sm h-[80%] bg-(--c-primary)/80"></div>
                        <div class="w-full rounded-t-sm h-[100%] bg-(--c-secondary)/80"></div>
                    </div>
                    <div class="flex flex-col gap-2 mt-2">
                        <div class="w-3/4 h-3 rounded-full" style="background:var(--c-glass-hover)"></div>
                        <div class="w-1/2 h-3 rounded-full" style="background:var(--c-glass)"></div>
                    </div>
                </div>

                {{-- Success badge card --}}
                <div class="absolute top-10 right-10 w-48 p-4 backdrop-blur-lg rounded-[var(--radius-md)] shadow-xl z-30 {{ $float ? 'animate-float-delayed' : '' }}"
                     style="background:color-mix(in srgb, var(--c-surface) 90%, transparent);border:1px solid var(--c-glass-border)">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-(--c-secondary) border border-(--c-secondary)/30 bg-(--c-secondary)/20">✓</div>
                        <div class="flex flex-col gap-1">
                            <div class="w-16 h-2 rounded-full" style="background:color-mix(in srgb, var(--c-text) 20%, transparent)"></div>
                            <div class="w-24 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
                        </div>
                    </div>
                </div>

                {{-- AI processing card --}}
                <div class="absolute bottom-16 left-0 w-56 p-4 backdrop-blur-lg rounded-[var(--radius-md)] shadow-xl z-10 flex gap-3 items-center {{ $float ? 'animate-float-fast' : '' }}"
                     style="background:var(--c-glass);border:1px solid var(--c-glass-border)">
                    <div class="w-12 h-12 rounded-[var(--radius-md)] flex items-center justify-center bg-linear-to-br from-(--c-primary) to-(--c-accent-orange)">
                        <div class="w-6 h-6 rounded-full animate-spin" style="border:2px solid color-mix(in srgb, var(--c-text) 50%, transparent);border-top-color:var(--c-text)"></div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div class="w-20 h-2 rounded-full" style="background:color-mix(in srgb, var(--c-text) 20%, transparent)"></div>
                        <div class="w-12 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
