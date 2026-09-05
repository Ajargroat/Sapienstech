{{-- resources/views/public/sections/advisor/default.blade.php — portrait + bio --}}
@php
    $a        = $cfg;
    $imgFirst = ($a['image_side'] ?? 'start') === 'start';
    $reveal   = $anim['reveal'] ?? true;
    $float    = $anim['float'] ?? true;
    $tagColor = 'var(--c-' . ($a['tagline_color'] ?? 'secondary') . ')';
@endphp
<section id="{{ $a['id'] ?? 'advisor' }}" class="lp-section overflow-hidden relative">

    @include('public.sections._glow-blobs', ['scope' => 'advisor'])

    <div class="landing-container">
        <div class="lp-card flex flex-col md:flex-row items-center gap-[var(--grid-gap)] p-8 md:p-12 relative z-10 overflow-hidden"
             style="background:var(--c-surface);border:var(--surface-border-w) solid var(--c-border);box-shadow:var(--card-shadow)">

            {{-- Decorative glow inside the card --}}
            <div class="absolute top-0 right-0 w-64 h-64 rounded-full blur-3xl -z-10 -translate-y-1/2 translate-x-1/4 bg-linear-to-br from-(--c-primary)/10 to-transparent"></div>

            {{-- Portrait --}}
            <div class="w-full md:w-2/5 flex justify-center {{ $reveal ? 'reveal fade-right' : '' }}"
                 style="{{ $imgFirst ? '' : 'order:2' }}">
                <div class="relative group">
                    @if ($a['spin_rings'] ?? true)
                        <div class="absolute inset-[-10px] rounded-full border border-dashed transition-colors duration-500 {{ $float ? 'spin-15s' : '' }}"
                             style="border-color:color-mix(in srgb, var(--c-primary) 30%, transparent)"></div>
                        <div class="absolute inset-[-20px] rounded-full border {{ $float ? 'spin-20s-rev' : '' }}"
                             style="border-color:color-mix(in srgb, var(--c-secondary) 20%, transparent)"></div>
                    @endif
                    <div class="relative rounded-full overflow-hidden border-4"
                         style="width:{{ $a['image_size'] ?? '18rem' }};height:{{ $a['image_size'] ?? '18rem' }};border-color:var(--c-surface-alt);background:var(--c-background)">
                        <img src="{{ tenant_asset($a['image']) }}" alt="{{ $a['image_alt'] ?? '' }}"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110 {{ ($a['grayscale'] ?? false) ? 'grayscale group-hover:grayscale-0' : '' }}">
                    </div>
                    @if ($a['badge']['visible'] ?? true)
                        <div class="absolute bottom-2 -right-4 backdrop-blur-md px-4 py-2 rounded-2xl shadow-xl flex items-center gap-2"
                             style="background:var(--c-glass-hover);border:1px solid var(--c-border-strong)">
                            <span class="w-3 h-3 rounded-full {{ $float ? 'animate-pulse' : '' }}" style="background:var(--c-success)"></span>
                            <span class="text-xs font-semibold">{{ $a['badge']['label'] ?? '' }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="w-full md:w-3/5 flex flex-col gap-6 text-center md:text-end {{ $reveal ? 'reveal fade-left' : '' }}"
                 style="{{ $imgFirst ? '' : 'order:1' }}">
                @if (!empty($a['eyebrow']))
                    <div class="lp-eyebrow w-max mx-auto md:mx-0">
                        <i class="{{ $a['eyebrow']['icon'] }} text-sm" aria-hidden="true"></i>
                        {{ $a['eyebrow']['label'] }}
                    </div>
                @endif

                <div>
                    <h2 style="font-size:var(--h2-size);font-weight:var(--heading-weight);letter-spacing:var(--heading-letter-spacing);text-transform:var(--heading-transform)">{{ $a['name'] }}</h2>
                    <p class="text-lg font-medium" style="color:{{ $tagColor }}">{{ $a['tagline'] }}</p>
                </div>

                <p class="text-base md:text-lg leading-relaxed" style="color:var(--c-muted);max-width:var(--measure)">{{ $a['bio'] }}</p>

                @if (!empty($a['stats']))
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 my-2">
                        @foreach ($a['stats'] as $s)
                            <div class="rounded-2xl p-4 text-center transition-colors"
                                 style="background:color-mix(in srgb, var(--c-background) 30%, transparent);border:1px solid var(--c-border)">
                                <div class="text-2xl font-bold mb-1">{{ $s['value'] }}</div>
                                <div class="text-xs" style="color:var(--c-subtle)">{{ $s['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pt-4 flex flex-col sm:flex-row items-center gap-4">
                    @foreach ($a['buttons'] ?? [] as $b)
                        @if ($b['visible'] ?? true)
                            @include('public.sections._button', [
                                'label' => $b['label'],
                                'href'  => $b['href'] ?? '#',
                                'tone'  => $b['style'] ?? null,
                                'block' => true,
                            ])
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
