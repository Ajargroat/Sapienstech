{{--
    resources/views/public/sections/testimonials/masonry.blade.php — column flow

    Cards pack by height instead of sitting in equal rows, so long and short
    quotes coexist without the gaps a strict grid produces.
--}}
@php
    $tm     = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $items  = array_values(array_filter($tm['items'] ?? [], static fn ($i) => $i['visible'] ?? true));
    $cols   = $tm['columns'] ?? min(count($items), 3);
@endphp
<section id="{{ $tm['id'] ?? 'testimonials' }}" class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $tm['heading'] ?? null,
            'subheading' => $tm['subheading'] ?? null,
        ])

        <div class="lp-masonry" style="--cols:{{ max(1, $cols) }}">
            @foreach ($items as $i => $item)
                <div class="lp-card {{ $reveal ? 'reveal' : '' }}"
                     style="transition-delay:{{ $i * (int) site('landing.stagger_ms', 100) }}ms">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="lp-avatar"
                             style="background:linear-gradient(45deg, var(--c-{{ $item['from'] ?? 'primary' }}), var(--c-{{ $item['to'] ?? 'secondary' }}))">
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
