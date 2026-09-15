{{-- resources/views/public/sections/blocks.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'blocks',
    'variant' => $L['blocks']['variant'] ?? 'default',
    'cfg'     => $L['blocks'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
