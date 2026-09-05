{{--
    resources/views/public/sections/hero/_mockup.blade.php

    The floating dashboard illustration, extracted from the hero so other hero
    variants can reuse it and so its hardcoded pieces read from tokens.
    The window chrome dots used to be literal bg-red-500/80 etc. — outside the
    tenant palette — and now come from the semantic colour tokens.
--}}
@php($float = $float ?? true)

{{-- Main chart card --}}
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-96 backdrop-blur-xl rounded-[var(--radius-lg)] p-6 shadow-2xl z-20 flex flex-col gap-4 {{ $float ? 'animate-float' : '' }} lp-card"
     style="background:color-mix(in srgb, var(--c-surface-alt) 80%, transparent);border:var(--surface-border-w) solid var(--c-glass-border)">
    <div class="flex justify-between items-center pb-4" style="border-bottom:1px solid var(--c-border)">
        <div class="flex gap-2">
            <div class="w-3 h-3 rounded-full" style="background:var(--c-danger);opacity:.8"></div>
            <div class="w-3 h-3 rounded-full" style="background:var(--c-warning);opacity:.8"></div>
            <div class="w-3 h-3 rounded-full" style="background:var(--c-success);opacity:.8"></div>
        </div>
        <div class="w-20 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
    </div>
    <div class="w-full h-24 rounded-[var(--radius-md)] flex items-end p-2 gap-1 bg-linear-to-r from-(--c-primary)/20 to-(--c-secondary)/20"
         style="border:1px solid var(--c-border)">
        <div class="w-full rounded-t-sm h-[40%] bg-(--c-primary)/80"></div>
        <div class="w-full rounded-t-sm h-[60%] bg-(--c-primary)/80"></div>
        <div class="w-full rounded-t-sm h-[30%] bg-(--c-primary)/80"></div>
        <div class="w-full rounded-t-sm h-[80%] bg-(--c-primary)/80"></div>
        <div class="w-full rounded-t-sm h-[100%] bg-(--c-secondary)/80"></div>
    </div>
    <div class="flex flex-col gap-2 mt-2">
        <div class="w-3/4 h-3 rounded-full" style="background:var(--c-glass-hover)"></div>
        <div class="w-1/2 h-3 rounded-full" style="background:var(--c-glass)"></div>
    </div>
</div>

{{-- Success badge card --}}
<div class="absolute top-10 right-10 w-48 p-4 backdrop-blur-lg rounded-[var(--radius-md)] shadow-xl z-30 {{ $float ? 'animate-float-delayed' : '' }}"
     style="background:color-mix(in srgb, var(--c-surface) 90%, transparent);border:var(--surface-border-w) solid var(--c-glass-border)">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center text-(--c-secondary) border border-(--c-secondary)/30 bg-(--c-secondary)/20">✓</div>
        <div class="flex flex-col gap-1">
            <div class="w-16 h-2 rounded-full" style="background:color-mix(in srgb, var(--c-text) 20%, transparent)"></div>
            <div class="w-24 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
        </div>
    </div>
</div>

{{-- AI processing card --}}
<div class="absolute bottom-16 left-0 w-56 p-4 backdrop-blur-lg rounded-[var(--radius-md)] shadow-xl z-10 flex gap-3 items-center {{ $float ? 'animate-float-fast' : '' }}"
     style="background:var(--c-glass);border:var(--surface-border-w) solid var(--c-glass-border)">
    <div class="w-12 h-12 rounded-[var(--radius-md)] flex items-center justify-center bg-linear-to-br from-(--c-primary) to-(--c-accent-orange)">
        <div class="w-6 h-6 rounded-full animate-spin" style="border:2px solid color-mix(in srgb, var(--c-text) 50%, transparent);border-top-color:var(--c-text)"></div>
    </div>
    <div class="flex flex-col gap-2">
        <div class="w-20 h-2 rounded-full" style="background:color-mix(in srgb, var(--c-text) 20%, transparent)"></div>
        <div class="w-12 h-2 rounded-full" style="background:var(--c-glass-hover)"></div>
    </div>
</div>
