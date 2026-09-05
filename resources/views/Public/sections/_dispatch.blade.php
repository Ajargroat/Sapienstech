{{--
    resources/views/Public/sections/_dispatch.blade.php

    Resolves which template renders a landing section, most-specific first:

        1. public/sections/{name}/tenants/{slug}.blade.php   one-off for a tenant
        2. public/sections/{name}/{variant}.blade.php        chosen by the archetype
        3. public/sections/{name}/default.blade.php          the platform default

    This is what lets an archetype change a page's *structure* and not just its
    colours — the lever that actually stops two tenants looking like the same
    product. A missing variant is not an error: @includeFirst falls through to
    the next candidate, so an archetype can name a variant before it exists and
    the section quietly renders its default.

    Expects: $name (section key), $variant, $cfg (that section's config),
             $L, $anim.
--}}
@php
    $base = 'public.sections.'.$name;
    $slug = tenant()?->slug;

    $candidates = array_values(array_filter([
        $slug    ? $base.'.tenants.'.$slug : null,
        $variant ? $base.'.'.$variant      : null,
        $base.'.default',
    ]));
@endphp

@includeFirst($candidates, [
    'cfg'   => $cfg ?? [],
    'name'  => $name,
    'L'     => $L,
    'anim'  => $anim,
])
