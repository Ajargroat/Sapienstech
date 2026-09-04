{{-- resources/views/public/sections/comparison.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'comparison',
    'variant' => $L['comparison']['variant'] ?? 'default',
    'cfg'     => $L['comparison'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
