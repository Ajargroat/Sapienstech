{{-- resources/views/public/sections/join.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'join',
    'variant' => $L['join']['variant'] ?? 'default',
    'cfg'     => $L['join'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
