{{-- resources/views/public/sections/nav.blade.php --}}
@php($nav = $public['nav'])
<nav id="site-nav" class="site-nav site-nav--{{ $nav['style'] }}{{ ($nav['sticky'] ?? true) ? '' : ' site-nav--static' }}">
    <div class="site-nav__inner">
        <div class="flex items-center gap-10">
            <a href="{{ route('home') }}" class="brand" style="font-size:1.5rem">
                @if ($nav['show_logo_mark'])
                    <span class="brand-mark brand-mark-small"></span>
                @endif
                {{ $tenant['name'] }}
            </a>

            <ul class="site-nav__links">
                @foreach ($nav['links'] as $link)
                    @if ($link['visible'] ?? true)
                        <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>

        @if ($nav['cta']['visible'])
            <a href="{{ route(auth()->check() ? 'consultant.dashboard' : $nav['cta']['route']) }}" class="nav-cta">
                {{ auth()->check() ? ($labels['dashboard'] ?? 'داشبورد') : $nav['cta']['label'] }}
                <i class="fa-solid fa-arrow-left text-xs transition-transform group-hover:-translate-x-1"></i>
            </a>
        @endif
    </div>
</nav>
