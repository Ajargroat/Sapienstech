{{-- resources/views/public/sections/stats.blade.php --}}
@php($st = $L['stats'])
@php($reveal = $anim['reveal'] ?? true)
<section class="px-[var(--container-padding)]" style="padding-top:5rem;padding-bottom:5rem">
    <div class="landing-container grid grid-cols-2 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-8 text-center"
         style="--cols:{{ $st['columns'] }}">
        @foreach ($st['items'] as $i => $s)
            @if ($s['visible'] ?? true)
                <div class="flex flex-col gap-2 {{ $reveal ? 'reveal fade-bottom' : '' }}"
                     style="transition-delay:{{ $i * (int) $theme['landing']['stagger_ms'] }}ms">
                    <span class="text-4xl md:text-5xl font-bold {{ $s['gradient'] ? 'text-transparent bg-clip-text bg-linear-to-l from-(--c-primary) to-(--c-secondary)' : '' }}">
                        <span data-counter="{{ $s['value'] }}">{{ persian_digits($s['value']) }}</span>{{ $s['suffix'] }}
                    </span>
                    <span class="text-2xl" style="color:var(--c-muted)">{{ $s['label'] }}</span>
                </div>
            @endif
        @endforeach
    </div>
</section>
