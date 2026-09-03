{{-- resources/views/public/sections/footer.blade.php --}}
@php($f = $public['footer'])
<footer class="px-[var(--container-padding)] border-t"
        style="background:var(--c-background);padding-top:5rem;padding-bottom:2.5rem;border-color:var(--c-glass-border)">
    <div class="landing-container grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
        <div class="md:col-span-2">
            <a href="{{ route('home') }}" class="text-2xl font-bold flex items-center gap-2 mb-6">
                @if ($f['show_logo_mark'])
                    <span class="brand-mark brand-mark-small"></span>
                @endif
                {{ $tenant['name'] }}
            </a>
            <p class="max-w-sm text-sm leading-relaxed mb-6" style="color:var(--c-subtle)">{{ $f['blurb'] }}</p>
            <div class="flex items-center gap-4" style="color:var(--c-subtle)">
                @foreach ($f['social'] as $s)
                    @if ($s['visible'] ?? true)
                        <a href="{{ $s['url'] }}" aria-label="{{ $s['label'] }}" class="transition-colors hover:text-(--c-text)">
                            <i class="{{ $s['icon'] }} text-xl"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
        @foreach ($f['columns'] as $col)
            <div>
                <h4 class="font-semibold mb-4">{{ $col['title'] }}</h4>
                <ul class="flex flex-col gap-3 text-sm list-none p-0 m-0" style="color:var(--c-subtle)">
                    @foreach ($col['links'] as $link)
                        <li><a href="{{ $link['href'] }}" class="transition-colors hover:text-(--c-primary)">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
    <div class="landing-container pt-8 border-t text-center text-xs" style="border-color:var(--c-border);color:var(--c-subtle)">
        <p>{{ str_replace([':name', ':year'], [$tenant['name'], persian_digits(now()->year)], $f['copyright']) }}</p>
    </div>
</footer>
