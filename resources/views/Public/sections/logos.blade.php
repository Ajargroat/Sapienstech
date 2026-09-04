{{-- resources/views/public/sections/logos.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'logos',
    'variant' => $L['logos']['variant'] ?? 'default',
    'cfg'     => $L['logos'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
