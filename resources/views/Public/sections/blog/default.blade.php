{{-- resources/views/public/sections/blog/default.blade.php — article grid --}}
@php
    $bl     = $cfg;
    $reveal = $anim['reveal'] ?? true;

    $seeAll = ($bl['see_all']['visible'] ?? true)
        ? '<a href="'.e($bl['see_all']['href'] ?? '#').'" class="hover:text-(--c-primary) transition-colors text-sm font-medium flex items-center gap-1">'
          .e($bl['see_all']['label'] ?? '')
          .' <i class="fa-solid fa-chevron-left text-xs" aria-hidden="true"></i></a>'
        : null;
@endphp
<section id="{{ $bl['id'] ?? 'blog' }}" class="lp-section lp-section--blog">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $bl['heading'] ?? null,
            'subheading' => $bl['subheading'] ?? null,
            'align'      => 'between',
            'action'     => $seeAll,
        ])

        <div class="grid grid-cols-1 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-[var(--grid-gap)]"
             style="--cols:{{ $bl['columns'] ?? 3 }}">
            @foreach ($bl['items'] ?? [] as $i => $post)
                @continue(! ($post['visible'] ?? true))
                <article class="lp-card group cursor-pointer p-5 {{ $reveal ? 'reveal' : '' }}"
                         style="transition-delay:{{ $i * (int) site('landing.stagger_ms', 100) }}ms">
                    <a href="{{ $post['url'] ?? '#' }}" class="block">
                        <div class="w-full h-48 rounded-2xl mb-6 overflow-hidden relative"
                             style="background:var(--c-surface-alt);border:1px solid var(--c-border)">
                            <div class="absolute inset-0 group-hover:scale-105 transition-transform duration-500"
                                 style="background:linear-gradient(135deg, color-mix(in srgb, var(--c-{{ $post['from'] ?? 'primary' }}) 10%, transparent), color-mix(in srgb, var(--c-{{ $post['to'] ?? 'secondary' }}) 10%, transparent))"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <img src="{{ tenant_asset($post['image']) }}" alt="{{ $post['title'] }}" class="max-w-full max-h-full object-cover" loading="lazy">
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-3">{{ $post['title'] }}</h3>
                        <p class="text-sm mb-4 line-clamp-2" style="color:var(--c-muted)">{{ $post['excerpt'] }}</p>
                    </a>
                </article>
            @endforeach
        </div>
    </div>
</section>
