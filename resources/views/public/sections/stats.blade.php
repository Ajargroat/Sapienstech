{{-- resources/views/public/sections/stats.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'stats',
    'variant' => $L['stats']['variant'] ?? 'default',
    'cfg'     => $L['stats'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
