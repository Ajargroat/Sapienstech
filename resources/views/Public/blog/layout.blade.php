{{--
    Public blog shell — same tenant theme as the landing page (theme-vars,
    theme-attrs, nav, footer) so blog pages are indistinguishable from the
    site they belong to. Nav/footer data comes from the ViewServiceProvider
    composer that also feeds public.landing.
--}}
@php
    $L    = $public['landing'] ?? [];
    $anim = $L['animations'] ?? ['reveal' => true];

    // Landing nav links are in-page anchors (#about); on a blog page they must
    // point back at the landing page's anchors.
    $nav = $public['nav'] ?? [];
    $nav['links'] = array_map(static function (array $link) {
        if (($link['href'] ?? '') !== '' && $link['href'][0] === '#') {
            $link['href'] = route('home').$link['href'];
        }
        return $link;
    }, $nav['links'] ?? []);
    $public = array_merge($public, ['nav' => $nav]);
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      @include('partials.theme-attrs')>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('blog-title', ($blogCfg['heading'] ?? 'وبلاگ').' | '.$tenant['name'])</title>

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
    @include('partials.preview-banner')

    <div class="lp-ground" aria-hidden="true"></div>

    @if ($nav['enabled'] ?? true)
        @include('public.sections.nav', ['L' => $L, 'anim' => $anim])
    @endif

    <main>
        @yield('blog-content')
    </main>

    @if ($public['footer']['enabled'] ?? true)
        @include('public.sections.footer', ['L' => $L, 'anim' => $anim])
    @endif
</body>
</html>
