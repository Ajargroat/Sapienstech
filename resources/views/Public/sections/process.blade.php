{{-- resources/views/public/sections/process.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'process',
    'variant' => $L['process']['variant'] ?? 'default',
    'cfg'     => $L['process'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
