{{-- resources/views/public/sections/nav.blade.php --}}
@php($nav = $public['nav'])
@php($brand = site('brand', []))
<nav id="site-nav" class="site-nav site-nav--{{ $nav['style'] ?? 'solid' }}{{ ($nav['sticky'] ?? true) ? '' : ' site-nav--static' }}">
    <div class="site-nav__inner">
        <div class="flex items-center gap-10">
            <a href="{{ route('home') }}" class="brand group" style="font-size:1.5rem">
                {{-- tenant.logo used to be settable and never rendered; the nav
                     always showed a text mark. Now an image logo works, with the
                     mark/word lockup selectable. --}}
                @if (!empty($brand['src']))
                    <img src="{{ tenant_asset($brand['src']) }}" alt="{{ $tenant['name'] }}"
                         style="height:{{ $brand['height'] ?? '2rem' }};width:auto" class="w-auto">
                @elseif ($nav['show_logo_mark'] ?? true)
                    <span class="brand-mark brand-mark-small"></span>
                @endif

                @if (($brand['variant'] ?? 'mark+word') !== 'mark')
                    {{ $tenant['name'] }}
                @endif
            </a>

            <ul class="site-nav__links">
                @foreach ($nav['links'] ?? [] as $link)
                    @if ($link['visible'] ?? true)
                        <li><a href="{{ $link['href'] }}">{{ $link['label'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>

        @if ($nav['cta']['visible'] ?? true)
            {{-- The arrow had `group-hover:-translate-x-1` with no `group`
                 ancestor, so the transform never fired. --}}
            <a href="{{ route(auth()->check() ? 'consultant.dashboard' : $nav['cta']['route']) }}"
               class="nav-cta group">
                {{ auth()->check() ? ($labels['dashboard'] ?? 'داشبورد') : ($nav['cta']['label'] ?? '') }}
                <i class="fa-solid fa-arrow-left text-xs transition-transform group-hover:-translate-x-1" aria-hidden="true"></i>
            </a>
        @endif
    </div>
</nav>
