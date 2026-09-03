{{-- resources/views/public/sections/advisor.blade.php --}}
@php
    $a        = $L['advisor'];
    $imgFirst = ($a['image_side'] ?? 'start') === 'start';
    $reveal   = $anim['reveal'] ?? true;
    $float    = $anim['float'] ?? true;
    $tagColor = 'var(--c-' . ($a['tagline_color'] ?? 'secondary') . ')';
@endphp
<section id="{{ $a['id'] }}" class="px-[var(--container-padding)] overflow-hidden relative" style="padding-top:var(--section-gap);padding-bottom:2rem">

    <div class="absolute rounded-full blur-[100px] pointer-events-none"
         style="top:50%;inset-inline-end:0;width:18rem;height:18rem;background:var(--c-primary);opacity:.05;transform:translateY(-50%)"></div>
    <div class="absolute rounded-full blur-[100px] pointer-events-none"
         style="bottom:0;inset-inline-start:2.5rem;width:18rem;height:18rem;background:var(--c-secondary);opacity:.05"></div>

    <div class="landing-container">
        <div class="flex flex-col md:flex-row items-center gap-[var(--grid-gap)] rounded-[2.5rem] p-8 md:p-12 relative z-10 shadow-2xl overflow-hidden"
             style="background:var(--c-surface);border:1px solid var(--c-border)">

            {{-- Decorative glow inside the card --}}
            <div class="absolute top-0 right-0 w-64 h-64 rounded-full blur-3xl -z-10 -translate-y-1/2 translate-x-1/4 bg-linear-to-br from-(--c-primary)/10 to-transparent"></div>

            {{-- Portrait --}}
            <div class="w-full md:w-2/5 flex justify-center {{ $reveal ? 'reveal fade-right' : '' }}"
                 style="{{ $imgFirst ? '' : 'order:2' }}">
                <div class="relative group">
                    @if ($a['spin_rings'])
                        <div class="absolute inset-[-10px] rounded-full border border-dashed transition-colors duration-500 {{ $float ? 'spin-15s' : '' }}"
                             style="border-color:color-mix(in srgb, var(--c-primary) 30%, transparent)"></div>
                        <div class="absolute inset-[-20px] rounded-full border {{ $float ? 'spin-20s-rev' : '' }}"
                             style="border-color:color-mix(in srgb, var(--c-secondary) 20%, transparent)"></div>
                    @endif
                    <div class="relative rounded-full overflow-hidden border-4"
                         style="width:{{ $a['image_size'] }};height:{{ $a['image_size'] }};border-color:var(--c-surface-alt);background:var(--c-background)">
                        <img src="{{ asset($a['image']) }}" alt="{{ $a['image_alt'] }}"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 {{ $a['grayscale'] ? 'grayscale group-hover:grayscale-0' : '' }}">
                    </div>
                    @if ($a['badge']['visible'] ?? true)
                        <div class="absolute bottom-2 -right-4 backdrop-blur-md px-4 py-2 rounded-2xl shadow-xl flex items-center gap-2"
                             style="background:var(--c-glass-hover);border:1px solid var(--c-border-strong)">
                            <span class="w-3 h-3 rounded-full {{ $float ? 'animate-pulse' : '' }}" style="background:var(--c-success)"></span>
                            <span class="text-xs font-semibold">{{ $a['badge']['label'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="w-full md:w-3/5 flex flex-col gap-6 text-center md:text-end {{ $reveal ? 'reveal fade-left' : '' }}"
                 style="{{ $imgFirst ? '' : 'order:1' }}">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-medium w-max mx-auto md:mx-0"
                     style="background:var(--c-primary-soft);border:1px solid color-mix(in srgb, var(--c-primary) 20%, transparent);color:var(--c-primary)">
                    <i class="{{ $a['eyebrow']['icon'] }} text-sm"></i>
                    {{ $a['eyebrow']['label'] }}
                </div>

                <div>
                    <h2 style="font-size:var(--h2-size);font-weight:var(--heading-weight);letter-spacing:-.01em">{{ $a['name'] }}</h2>
                    <p class="text-lg font-medium" style="color:{{ $tagColor }}">{{ $a['tagline'] }}</p>
                </div>

                <p class="text-base md:text-lg leading-relaxed max-w-2xl" style="color:var(--c-muted)">{{ $a['bio'] }}</p>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 my-2">
                    @foreach ($a['stats'] as $s)
                        <div class="rounded-2xl p-4 text-center transition-colors"
                             style="background:color-mix(in srgb, var(--c-background) 30%, transparent);border:1px solid var(--c-border)">
                            <div class="text-2xl font-bold mb-1">{{ $s['value'] }}</div>
                            <div class="text-xs" style="color:var(--c-subtle)">{{ $s['label'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-4 flex flex-col sm:flex-row items-center gap-4">
                    @foreach ($a['buttons'] as $b)
                        @if ($b['visible'] ?? true)
                            <a href="{{ $b['href'] }}"
                               class="w-full sm:w-auto px-8 py-3.5 rounded-[var(--radius-button)] font-semibold text-center transition-colors flex items-center justify-center gap-2"
                               style="{{ $b['style'] === 'primary'
                                   ? 'background:var(--c-primary);color:var(--c-on-primary)'
                                   : 'background:var(--c-glass);border:1px solid var(--c-glass-border);color:var(--c-text)' }}">
                                {{ $b['label'] }}
                                @if ($b['style'] !== 'primary')
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
