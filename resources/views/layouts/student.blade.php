<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant['name'] }} | پنل دانش‌آموز</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">
    <link rel="stylesheet" href="{{ $theme['assets']['icon_library_url'] }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="student-shell" @include('partials.theme-attrs')>
    @include('partials.color-scheme', ['schemeKey' => 'student-color-scheme'])

    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>

    <div class="min-h-screen relative z-10">
        @include('components.student.topnav')

        <div class="consultant-content">
            <main class="content-container" id="app-content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
