<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant['name'] }} | {{ $tenant['page_title'] }}</title>




    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @include('partials.studio-node-styles')
</head>
<body class="consultant-shell" @include('partials.theme-attrs')>
    @include('partials.color-scheme', ['schemeKey' => 'consultant-color-scheme'])
    @include('partials.preview-banner')

    <div class="page-glow page-glow-primary" data-studio-path="public.consultant.glow_primary"></div>
    <div class="page-glow page-glow-secondary" data-studio-path="public.consultant.glow_secondary"></div>

    <div class="min-h-screen relative z-10">
        {{-- The tenant chooses where the panel tabs live: theme.layout.shell_nav
             (Appearance studio → «جای منوی پنل»). The ternary normalises any
             unexpected value back to the topnav, so the include path is always
             one of two literals. --}}
        @php $shellNav = site('theme.layout.shell_nav') === 'sidebar' ? 'sidebar' : 'topnav'; @endphp
        {{-- Studio ids: the dashboard is a canvas page like the landing, so its
             shell carries stable data-studio-path ids under the consultant
             root. The marker makes the nav an addressable section; the content
             region is one too. These roots are template-owned — config/studio.php
             declares no fields for them (see StudioStyles::TEMPLATE_ROOTS). --}}
        <template data-studio-section-marker="nav"></template>
        @include('components.consultant.'.$shellNav)

        <template data-studio-section-marker="content"></template>
        <div class="consultant-content" data-studio-path="public.consultant.content">
            <main class="content-container" id="app-content" data-studio-path="public.consultant.content.main">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
