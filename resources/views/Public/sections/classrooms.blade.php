{{-- resources/views/public/sections/classrooms.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'classrooms',
    'variant' => $L['classrooms']['variant'] ?? 'default',
    'cfg'     => $L['classrooms'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
