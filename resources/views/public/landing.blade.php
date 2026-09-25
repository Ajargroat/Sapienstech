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
    <meta property="og:title" content="{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? $tenant['short_name'] }}">
    <meta property="og:description" content="{{ $L['meta']['description'] ?? '' }}">
    @if (!empty($L['meta']['og_image']))
        <meta property="og:image" content="{{ tenant_asset($L['meta']['og_image']) }}">
        <meta name="twitter:image" content="{{ tenant_asset($L['meta']['og_image']) }}">
    @endif

    @if (!empty($theme['brand']['src']))
        <link rel="icon" href="{{ tenant_asset($theme['brand']['src']) }}">
    @elseif (!empty($tenant['favicon']))
        <link rel="icon" href="{{ $tenant['favicon'] }}">
    @endif




    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    {{-- Per-element overrides: one stylesheet for the whole page, emitted only
         when the tenant has stored any. The canvas document (local rules,
         pseudo-class states, breakpoints, hidden nodes) is emitted after the
         legacy list, so a node override wins for the same element. Both are
         keyed by data-studio-path, so neither needs cooperation from the
         section partials. --}}
    @php($lpOverrides = \App\Support\StudioStyles::css($L['overrides'] ?? []).\App\Support\StudioStyles::nodesCss($public['canvas']['nodes'] ?? null))
    @if ($lpOverrides !== '')
        <style id="lp-element-overrides">{!! $lpOverrides !!}</style>
    @endif
</head>
<body class="landing">
    @include('partials.color-scheme', ['schemeKey' => 'public-color-scheme'])

    @include('partials.preview-banner')

    {{-- Page ground. Replaces the per-section blobs the partials used to
         hand-place; `background.mode` decides what renders here. --}}
    <div class="lp-ground" aria-hidden="true"></div>

    @if ($public['nav']['enabled'])
        <template data-studio-section-marker="nav"></template>
        @include('public.sections.nav', ['L' => $L, 'anim' => $anim])
    @endif

    <main>
        @foreach ($L['sections'] as $section)
            {{--
                Each section dispatches to a variant partial of its own
                (public/sections/{name}/{variant}.blade.php); the dispatcher
                partial resolves which one and falls back to the default.
            --}}
            <template data-studio-section-marker="{{ $section }}"></template>
            @includeIf('public.sections.' . $section, ['L' => $L, 'anim' => $anim])
        @endforeach
    </main>

    @if ($public['footer']['enabled'])
        <template data-studio-section-marker="footer"></template>
        @include('public.sections.footer', ['L' => $L, 'anim' => $anim])
    @endif

</body>
</html>
