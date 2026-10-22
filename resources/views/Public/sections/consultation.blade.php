{{-- resources/views/public/sections/consultation.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'consultation',
    'variant' => $L['consultation']['variant'] ?? 'default',
    'cfg'     => $L['consultation'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
