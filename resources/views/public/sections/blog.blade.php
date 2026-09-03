{{-- resources/views/public/sections/blog.blade.php --}}
@php
    $bl     = $L['blog'];
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $bl['id'] }}" class="px-[var(--container-padding)] bg-linear-to-l from-(--c-surface) to-(--c-background)"
         style="padding-top:var(--section-gap);padding-bottom:var(--section-gap)">
    <div class="landing-container">
        <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-2 {{ $reveal ? 'reveal fade-bottom' : '' }}">
            <div>
                <h2 class="mb-4" style="font-size:var(--h2-size);font-weight:var(--heading-weight)">{{ $bl['heading'] }}</h2>
                <p style="color:var(--c-muted)">{{ $bl['subheading'] }}</p>
            </div>
            @if ($bl['see_all']['visible'] ?? true)
                <a href="{{ $bl['see_all']['href'] }}" class="hover:text-(--c-primary) transition-colors text-sm font-medium flex items-center gap-1">
                    {{ $bl['see_all']['label'] }}
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-8" style="--cols:{{ $bl['columns'] }}">
            @foreach ($bl['items'] as $i => $post)
                @continue(! ($post['visible'] ?? true))
                <article class="group cursor-pointer hover:-translate-y-2 border border-transparent hover:border-(--c-border-strong) p-5 rounded-[var(--radius-card)] {{ $reveal ? 'reveal fade-bottom' : '' }}"
                         style="transition-delay:{{ $i * (int) $theme['landing']['stagger_ms'] }}ms">
                    <a href="{{ $post['url'] ?? '#' }}" class="block">
                        <div class="w-full h-48 rounded-2xl mb-6 overflow-hidden relative"
                             style="background:var(--c-surface-alt);border:1px solid var(--c-border)">
                            <div class="absolute inset-0 group-hover:scale-105 transition-transform duration-500"
                                 style="background:linear-gradient(135deg, color-mix(in srgb, var(--c-{{ $post['from'] }}) 10%, transparent), color-mix(in srgb, var(--c-{{ $post['to'] }}) 10%, transparent))"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <img src="{{ asset($post['image']) }}" alt="{{ $post['title'] }}" class="max-w-full max-h-full object-cover">
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
