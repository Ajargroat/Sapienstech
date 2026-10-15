{{-- resources/views/public/sections/plans.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'plans',
    'variant' => $L['plans']['variant'] ?? 'default',
    'cfg'     => $L['plans'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
