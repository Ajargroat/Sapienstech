{{-- resources/views/public/sections/blog/list.blade.php — stacked rows, not cards --}}
@php
    $bl     = $cfg;
    $reveal = $anim['reveal'] ?? true;
@endphp
<section id="{{ $bl['id'] ?? 'blog' }}" class="lp-section">
    <div class="landing-container" style="max-width:var(--measure);margin-inline:auto">
        @include('public.sections._heading', [
            'heading'    => $bl['heading'] ?? null,
            'subheading' => $bl['subheading'] ?? null,
            'align'      => 'start',
        ])

        <div class="lp-post-list">
            @foreach ($bl['items'] ?? [] as $post)
                @continue(! ($post['visible'] ?? true))
                <a href="{{ $post['url'] ?? '#' }}" class="lp-post-list__row {{ $reveal ? 'reveal' : '' }}">
                    @if (!empty($post['image']))
                        <img src="{{ tenant_asset($post['image']) }}" alt="" class="lp-post-list__thumb" loading="lazy">
                    @endif
                    <span class="lp-post-list__body">
                        <span class="lp-post-list__title">{{ $post['title'] }}</span>
                        <span class="lp-post-list__excerpt">{{ $post['excerpt'] }}</span>
                    </span>
                    <i class="fa-solid fa-chevron-left shrink-0" aria-hidden="true" style="color:var(--c-subtle)"></i>
                </a>
            @endforeach
        </div>
    </div>
</section>
