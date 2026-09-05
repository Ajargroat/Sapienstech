{{-- resources/views/public/sections/faq.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'faq',
    'variant' => $L['faq']['variant'] ?? 'default',
    'cfg'     => $L['faq'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
