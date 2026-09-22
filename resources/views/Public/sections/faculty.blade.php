{{-- resources/views/public/sections/faculty.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'faculty',
    'variant' => $L['faculty']['variant'] ?? 'default',
    'cfg'     => $L['faculty'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
