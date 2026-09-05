{{-- resources/views/public/sections/advisor.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'advisor',
    'variant' => $L['advisor']['variant'] ?? 'default',
    'cfg'     => $L['advisor'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
