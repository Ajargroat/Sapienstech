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
</head>
<body 
    class="consultant-shell" 
    style="
        --c-primary: {{ $theme['colors']['primary'] }};
        --c-primary-hover: {{ $theme['colors']['primary_hover'] }};
        --c-secondary: {{ $theme['colors']['secondary'] }};
        --c-background: {{ $theme['colors']['background'] }};
        --c-surface: {{ $theme['colors']['surface'] }};
        --c-surface-alt: {{ $theme['colors']['surface_alt'] }};
        --c-surface-elevated: {{ $theme['colors']['surface_elevated'] }};
        --c-text: {{ $theme['colors']['text'] }};
        --c-muted: {{ $theme['colors']['text_muted'] }};
        --c-border: {{ $theme['colors']['border'] }};
        --c-border-strong: {{ $theme['colors']['border_strong'] }};
        --c-success: {{ $theme['colors']['success'] }};
        --c-info: {{ $theme['colors']['info'] }};
        --c-danger: {{ $theme['colors']['danger'] }};
        --radius-card: {{ $theme['shape']['card_radius'] }};
        --radius-sidebar: {{ $theme['shape']['sidebar_radius'] }};
        --sidebar-width: {{ $theme['layout']['sidebar_width'] }};
        --content-max: {{ $theme['layout']['content_max_width'] }};
        --content-padding: {{ $theme['layout']['content_padding'] }};
        --backdrop-blur: {{ $theme['effects']['backdrop_blur'] }};
        --brand-gradient: {{ $theme['gradients']['brand'] }};
        --animation-duration: {{ $theme['effects']['animation_duration'] }};
    " 
    data-theme-preset="{{ $theme['preset'] }}"
>
    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>
    
    <div class="min-h-screen relative z-10">
        @include('components.consultant.sidebar')
        
        <div id="sidebar-overlay" class="sidebar-overlay"></div>
        
        <div class="consultant-content">
            <div class="mobile-topbar">
                <a href="{{ route('consultant.dashboard') }}" class="brand-mini">
                    <span class="brand-mark brand-mark-small"></span>
                    {{ $tenant['short_name'] }}
                </a>
                <button id="open-sidebar-btn" class="icon-button" type="button">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <main class="content-container">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>