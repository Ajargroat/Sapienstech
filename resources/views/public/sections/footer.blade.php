{{-- resources/views/Public/sections/footer.blade.php --}}
@php
    $f = $public['footer'];

    // The template used `md:grid-cols-4` with the brand block spanning 2, which
    // silently assumed exactly two link columns; a third column overflowed.
    $gridCols = count($f['columns'] ?? []) + 2;

    // Structure lever: columns (classic multi-column), centered (brand above,
    // columns below, all on the centre axis), minimal (identity + social only).
    $variant    = $f['variant'] ?? 'columns';
    $background = [
        'surface'    => 'var(--c-surface)',
        'gradient'   => 'var(--brand-gradient)',
    ][$f['background'] ?? 'background'] ?? 'var(--c-background)';
    $onGradient = ($f['background'] ?? 'background') === 'gradient';
@endphp
<footer class="lp-footer lp-footer--{{ $variant }} px-[var(--container-padding)] border-t"
        style="background:{{ $background }};padding-top:var(--section-gap);padding-bottom:2.5rem;border-color:var(--c-glass-border){{ $onGradient ? ';color:var(--c-on-primary)' : '' }}">
    <div class="landing-container lp-footer-grid mb-16" style="--cols:{{ $gridCols }}">
        <div class="lp-footer-grid__brand">
            <a href="{{ route('home') }}" class="text-2xl font-bold flex items-center gap-2 mb-6">
                @if ($f['show_logo_mark'])
                    <span class="brand-mark brand-mark-small"></span>
                @endif
                {{ $tenant['name'] }}
            </a>
            @if ($variant !== 'minimal')
                <p class="max-w-sm text-sm leading-relaxed mb-6" style="color:{{ $onGradient ? 'inherit' : 'var(--c-subtle)' }}">{{ $f['blurb'] }}</p>
            @endif
            <div class="flex items-center gap-4" style="color:{{ $onGradient ? 'inherit' : 'var(--c-subtle)' }}">
                @foreach ($f['social'] as $s)
                    @if ($s['visible'] ?? true)
                        <a href="{{ $s['url'] }}" aria-label="{{ $s['label'] }}" class="transition-colors hover:text-(--c-text)">
                            <i class="{{ $s['icon'] }} text-xl"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
        @if ($variant !== 'minimal')
            <div class="lp-footer-grid__cols">
                @foreach ($f['columns'] as $col)
                    <div>
                        <h4 class="font-semibold mb-4">{{ $col['title'] }}</h4>
                        <ul class="flex flex-col gap-3 text-sm list-none p-0 m-0" style="color:{{ $onGradient ? 'inherit' : 'var(--c-subtle)' }}">
                            @foreach ($col['links'] as $link)
                                <li><a href="{{ $link['href'] }}" class="transition-colors hover:text-(--c-link)">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="landing-container pt-8 border-t text-center text-xs" style="border-color:var(--c-border);color:{{ $onGradient ? 'inherit' : 'var(--c-subtle)' }}">
        <p>{{ str_replace([':name', ':year'], [$tenant['name'], persian_digits(now()->year)], $f['copyright']) }}</p>
    </div>
</footer>
