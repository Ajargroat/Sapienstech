{{-- resources/views/public/sections/testimonials/default.blade.php — card grid --}}
@php
    $tm     = $cfg;
    $reveal = $anim['reveal'] ?? true;
    $items  = array_values(array_filter($tm['items'] ?? [], static fn ($i) => $i['visible'] ?? true));

    // Was derived from the item count, so a tenant with 5 testimonials got an
    // unreadable 5-column grid. `columns` now wins when set.
    $cols = $tm['columns'] ?? min(count($items), 3);
@endphp
<section id="{{ $tm['id'] ?? 'testimonials' }}" class="lp-section overflow-hidden">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $tm['heading'] ?? null,
            'subheading' => $tm['subheading'] ?? null,
        ])

        <div class="flex overflow-x-auto snap-x snap-mandatory gap-[var(--grid-gap)] pb-8 hide-scrollbar cursor-grab active:cursor-grabbing md:grid md:overflow-visible"
             style="grid-template-columns:repeat({{ max(1, $cols) }}, minmax(0, 1fr))">
            @foreach ($items as $i => $item)
                <div class="lp-card min-w-[300px] w-full snap-center p-[var(--card-padding)] {{ $reveal ? 'reveal' : '' }}"
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
