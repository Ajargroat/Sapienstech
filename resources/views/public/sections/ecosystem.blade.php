{{-- resources/views/public/sections/ecosystem.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'ecosystem',
    'variant' => $L['ecosystem']['variant'] ?? 'default',
    'cfg'     => $L['ecosystem'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
