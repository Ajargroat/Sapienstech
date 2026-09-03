{{-- resources/views/public/landing.blade.php --}}
{{-- Config-driven landing page. Section order/visibility comes from
     config('consultant.public.landing.sections'); each section is a partial
     under public/sections/. All branding flows through theme tokens. --}}
@php
    $L    = $public['landing'];
    $anim = $L['animations'];
@endphp
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}"
      data-numerals="{{ $tenant['locale'] === 'fa' ? 'fa' : 'en' }}"
      data-animations="{{ ($theme['effects']['enable_animations'] ?? true) ? 'on' : 'off' }}"
      data-counter-duration="{{ $theme['landing']['counter_duration'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant['name'] }} | {{ $L['meta']['title'] ?? $tenant['short_name'] }}</title>
    <meta name="description" content="{{ $L['meta']['description'] ?? '' }}">

    @if (!empty($tenant['favicon']))
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

    @if ($public['nav']['enabled'])
        @include('public.sections.nav', ['L' => $L, 'anim' => $anim])
    @endif

    <main>
        @foreach ($L['sections'] as $section)
            @includeIf('public.sections.' . $section, ['L' => $L, 'anim' => $anim])
        @endforeach
    </main>

    @if ($public['footer']['enabled'])
        @include('public.sections.footer', ['L' => $L, 'anim' => $anim])
    @endif

</body>
</html>
