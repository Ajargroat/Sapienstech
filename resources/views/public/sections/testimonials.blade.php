{{-- resources/views/public/sections/testimonials.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'testimonials',
    'variant' => $L['testimonials']['variant'] ?? 'default',
    'cfg'     => $L['testimonials'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
