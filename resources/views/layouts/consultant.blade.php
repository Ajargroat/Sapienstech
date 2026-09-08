<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant['name'] }} | {{ $tenant['page_title'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">
    <link rel="stylesheet" href="{{ $theme['assets']['icon_library_url'] }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="consultant-shell" @include('partials.theme-attrs')>
    @include('partials.color-scheme', ['schemeKey' => 'consultant-color-scheme'])
    @include('partials.preview-banner')

    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>

    <div class="min-h-screen relative z-10">
        {{-- The tenant chooses where the panel tabs live: theme.layout.shell_nav
             (Appearance studio → «جای منوی پنل»). The ternary normalises any
             unexpected value back to the topnav, so the include path is always
             one of two literals. --}}
        @php $shellNav = site('theme.layout.shell_nav') === 'sidebar' ? 'sidebar' : 'topnav'; @endphp
        @include('components.consultant.'.$shellNav)

        <div class="consultant-content">
            <main class="content-container" id="app-content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
