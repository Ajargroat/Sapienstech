{{-- resources/views/public/landing.blade.php --}}
{{-- Config-driven landing page. Section order/visibility comes from
     public.landing.sections; each section is a partial under public/sections/.
     All branding flows through theme tokens (partials.theme-vars) and theme
     behaviour through data attributes (partials.theme-attrs). --}}
@php
    $L    = $public['landing'];
    $anim = $L['animations'];

    // `auto` keeps the historic behaviour of following the tenant locale.
    $numerals = $theme['i18n']['numerals'] ?? 'auto';
    if ($numerals === 'auto') {
        $numerals = ($tenant['locale'] ?? 'fa') === 'fa' ? 'fa' : 'en';
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-numerals="{{ $numerals }}"
      data-animations="{{ ($theme['effects']['enable_animations'] ?? true) ? 'on' : 'off' }}"
      data-counter-duration="{{ $theme['landing']['counter_duration'] }}"
      @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? $tenant['short_name'] }}</title>
    <meta name="description" content="{{ $L['meta']['description'] ?? '' }}">

    @if (!empty($theme['brand']['src']))
        <link rel="icon" href="{{ tenant_asset($theme['brand']['src']) }}">
    @elseif (!empty($tenant['favicon']))
        <link rel="icon" href="{{ $tenant['favicon'] }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">
    <link rel="stylesheet" href="{{ $theme['assets']['icon_library_url'] }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="landing">

    {{-- Page ground. Replaces the per-section blobs the partials used to
         hand-place; `background.mode` decides what renders here. --}}
    <div class="lp-ground" aria-hidden="true"></div>

    @if ($public['nav']['enabled'])
        @include('public.sections.nav', ['L' => $L, 'anim' => $anim])
    @endif

    <main>
        @foreach ($L['sections'] as $section)
            {{--
                Each section dispatches to a variant partial of its own
                (public/sections/{name}/{variant}.blade.php); the dispatcher
                partial resolves which one and falls back to the default.
            --}}
            @includeIf('public.sections.' . $section, ['L' => $L, 'anim' => $anim])
        @endforeach
    </main>

    @if ($public['footer']['enabled'])
        @include('public.sections.footer', ['L' => $L, 'anim' => $anim])
    @endif

</body>
</html>
