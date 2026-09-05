{{-- resources/views/public/sections/cta.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'cta',
    'variant' => $L['cta']['variant'] ?? 'default',
    'cfg'     => $L['cta'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
