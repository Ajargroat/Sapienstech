{{-- resources/views/public/sections/services.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'services',
    'variant' => $L['services']['variant'] ?? 'default',
    'cfg'     => $L['services'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
