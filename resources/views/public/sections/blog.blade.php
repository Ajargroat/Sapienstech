{{-- resources/views/public/sections/blog.blade.php — variant dispatcher --}}
@include('public.sections._dispatch', [
    'name'    => 'blog',
    'variant' => $L['blog']['variant'] ?? 'default',
    'cfg'     => $L['blog'] ?? [],
    'L'       => $L,
    'anim'    => $anim,
])
