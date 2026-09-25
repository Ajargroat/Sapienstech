<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant['name'] }} | پنل دانش‌آموز</title>




    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
    @include('partials.studio-node-styles')
</head>
<body class="student-shell" @include('partials.theme-attrs')>
    @include('partials.color-scheme', ['schemeKey' => 'student-color-scheme'])

    <div class="page-glow page-glow-primary" data-studio-path="public.student.glow_primary"></div>
    <div class="page-glow page-glow-secondary" data-studio-path="public.student.glow_secondary"></div>

    <div class="min-h-screen relative z-10">
        {{-- Same tenant-chosen shell nav as the consultant portal:
             theme.layout.shell_nav (topnav | sidebar). --}}
        @php $shellNav = site('theme.layout.shell_nav') === 'sidebar' ? 'sidebar' : 'topnav'; @endphp
        {{-- Studio ids under the student root; see layouts/consultant.blade.php
             for the template-owned namespace contract. --}}
        <template data-studio-section-marker="nav"></template>
        @include('components.student.'.$shellNav)

        <template data-studio-section-marker="content"></template>
        <div class="consultant-content" data-studio-path="public.student.content">
            <main class="content-container" id="app-content" data-studio-path="public.student.content.main">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
