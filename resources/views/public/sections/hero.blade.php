{{-- resources/views/public/sections/hero.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'hero',
    'variant' => $L['hero']['layout'] ?? 'default',
    'cfg'     => $L['hero'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
