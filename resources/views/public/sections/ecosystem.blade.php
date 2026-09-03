{{-- resources/views/public/sections/ecosystem.blade.php --}}
@php
    $eco       = $L['ecosystem'];
    $textFirst = ($eco['text_side'] ?? 'end') === 'start';
    $reveal    = $anim['reveal'] ?? true;
    $float     = $anim['float'] ?? true;
@endphp
<section class="px-[var(--container-padding)] bg-linear-to-b from-(--c-background) to-(--c-surface-alt)"
         style="padding-top:2rem;padding-bottom:var(--section-gap)">
    <div class="landing-container grid md:grid-cols-2 gap-16 items-center">

        {{-- Decorative orbiting visual --}}
        @if ($eco['visual'])
            <div class="{{ $reveal ? 'reveal fade-right' : '' }} relative" style="{{ $textFirst ? 'order:2' : '' }}">
                <div class="aspect-square max-w-md mx-auto relative">
                    <div class="absolute inset-0 rounded-full {{ $float ? 'spin-10s' : '' }}" style="border:1px solid var(--c-glass-border)"></div>
                    <div class="absolute inset-4 rounded-full {{ $float ? 'spin-15s-rev' : '' }}" style="border:1px solid var(--c-glass-border)"></div>
                    <div class="absolute inset-8 rounded-full" style="border:1px solid color-mix(in srgb, var(--c-primary) 20%, transparent)"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-32 h-32 rounded-2xl blur-sm opacity-50 bg-linear-to-br from-(--c-primary) to-(--c-secondary)"></div>
                        <div class="absolute w-32 h-32 rounded-2xl flex items-center justify-center shadow-2xl"
                             style="background:var(--c-surface-alt);border:1px solid var(--c-glass-border)">
                            <i class="fa-solid fa-desktop text-4xl" style="color:color-mix(in srgb, var(--c-text) 80%, transparent)"></i>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Copy --}}
        <div class="flex flex-col gap-6 {{ $reveal ? 'reveal fade-left' : '' }}" style="{{ $textFirst ? 'order:1' : '' }}">
            <h2 style="font-size:var(--h2-size);font-weight:var(--heading-weight);letter-spacing:-.01em">{{ $eco['heading'] }}</h2>
            <p class="text-lg leading-relaxed" style="color:var(--c-muted)">{{ $eco['text'] }}</p>
            <ul class="flex flex-col gap-4 mt-4 list-none p-0 m-0">
                @foreach ($eco['items'] as $item)
                    @php($accent = 'var(--c-' . ($item['accent'] ?? 'primary') . ')')
                    <li class="flex items-center gap-3">
                        <div class="w-6 h-6 rounded-full flex items-center justify-center text-sm"
                             style="background:color-mix(in srgb, {{ $accent }} 10%, transparent);color:{{ $accent }}">
                            {{ $item['icon'] }}
                        </div>
                        <span style="color:color-mix(in srgb, var(--c-text) 85%, transparent)">{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
