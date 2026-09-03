{{-- resources/views/partials/theme-vars.blade.php --}}
{{-- Single source of truth for tenant branding CSS variables. Included by the
     landing page, login, consultant layout and student layout so every entry
     point renders with the exact same palette, type and shape tokens. --}}
@php($t = config('consultant.theme'))
<style>
    :root {
        /* colors */
        --c-primary: {{ $t['colors']['primary'] }};
        --c-primary-hover: {{ $t['colors']['primary_hover'] }};
        --c-secondary: {{ $t['colors']['secondary'] }};
        --c-secondary-hover: {{ $t['colors']['secondary_hover'] }};
        --c-background: {{ $t['colors']['background'] }};
        --c-surface: {{ $t['colors']['surface'] }};
        --c-surface-alt: {{ $t['colors']['surface_alt'] }};
        --c-surface-elevated: {{ $t['colors']['surface_elevated'] }};
        --c-text: {{ $t['colors']['text'] }};
        --c-muted: {{ $t['colors']['text_muted'] }};
        --c-subtle: {{ $t['colors']['text_subtle'] }};
        --c-border: {{ $t['colors']['border'] }};
        --c-border-strong: {{ $t['colors']['border_strong'] }};
        --c-success: {{ $t['colors']['success'] }};
        --c-info: {{ $t['colors']['info'] }};
        --c-warning: {{ $t['colors']['warning'] }};
        --c-danger: {{ $t['colors']['danger'] }};
        --c-primary-soft: {{ $t['colors']['primary_soft'] }};
        --c-secondary-soft: {{ $t['colors']['secondary_soft'] }};
        --c-accent-blue: {{ $t['colors']['accent_blue'] }};
        --c-accent-emerald: {{ $t['colors']['accent_emerald'] }};
        --c-accent-orange: {{ $t['colors']['accent_orange'] }};
        --c-accent-teal: {{ $t['colors']['accent_teal'] }};
        --c-accent-red: {{ $t['colors']['accent_red'] }};
        --c-glass: {{ $t['colors']['glass'] }};
        --c-glass-hover: {{ $t['colors']['glass_hover'] }};
        --c-glass-border: {{ $t['colors']['glass_border'] }};
        --c-on-primary: {{ $t['colors']['on_primary'] }};
        --c-on-surface: {{ $t['colors']['on_surface'] }};
        --c-selection-bg: {{ $t['colors']['selection_background'] }};
        --c-selection-text: {{ $t['colors']['selection_text'] }};

        /* typography */
        --font-body: {{ $t['typography']['font_family'] }}, sans-serif;
        --body-size: {{ $t['typography']['body_size'] }};
        --body-weight: {{ $t['typography']['body_weight'] }};
        --heading-weight: {{ $t['typography']['heading_weight'] }};
        --letter-spacing: {{ $t['typography']['letter_spacing'] }};
        --line-height: {{ $t['typography']['line_height'] }};
        --h1-size: {{ $t['typography']['h1_size'] }};
        --h2-size: {{ $t['typography']['h2_size'] }};
        --h3-size: {{ $t['typography']['h3_size'] }};
        --hero-line-height: {{ $t['typography']['hero_line_height'] }};

        /* shape */
        --radius-sm: {{ $t['shape']['radius_sm'] }};
        --radius-md: {{ $t['shape']['radius_md'] }};
        --radius-lg: {{ $t['shape']['radius_lg'] }};
        --radius-xl: {{ $t['shape']['radius_xl'] }};
        --radius-2xl: {{ $t['shape']['radius_2xl'] }};
        --radius-button: {{ $t['shape']['button_radius'] }};
        --radius-input: {{ $t['shape']['input_radius'] }};
        --radius-card: {{ $t['shape']['card_radius'] }};

        /* layout */
        --sidebar-width: {{ $t['layout']['sidebar_width'] }};
        --content-max: {{ $t['layout']['content_max_width'] }};
        --content-padding: {{ $t['layout']['content_padding'] }};
        --topnav-height: {{ $t['layout']['topnav_height'] }};
        --card-gap: {{ $t['layout']['card_gap'] }};
        --section-gap: {{ $t['spacing']['section_gap'] }};
        --container-max: {{ $t['spacing']['container_max'] }};
        --container-padding: {{ $t['spacing']['container_padding'] }};
        --grid-gap: {{ $t['spacing']['grid_gap'] }};
        --card-padding: {{ $t['spacing']['card_padding'] }};

        /* effects */
        --shadow: {{ $t['effects']['shadow'] }};
        --card-shadow: {{ $t['effects']['card_shadow'] }};
        --sidebar-shadow: {{ $t['effects']['sidebar_shadow'] }};
        --backdrop-blur: {{ $t['effects']['backdrop_blur'] }};
        --glass-opacity: {{ $t['effects']['glass_opacity'] }};
        --hover-lift: {{ $t['effects']['hover_lift'] }};
        --animation-duration: {{ $t['effects']['animation_duration'] }};

        /* gradients */
        --brand-gradient: {{ $t['gradients']['brand'] }};
        --page-glow-1: {{ $t['gradients']['page_glow_1'] }};
        --page-glow-2: {{ $t['gradients']['page_glow_2'] }};

        /* landing */
        --nav-height: {{ $t['landing']['nav_height'] }};
        --hero-min-height: {{ $t['landing']['hero_min_height'] }};
        --glow-size: {{ $t['landing']['glow_size'] }};
        --glow-blur: {{ $t['landing']['glow_blur'] }};
        --glow-opacity: {{ $t['landing']['glow_opacity'] }};
        --reveal-duration: {{ $t['landing']['reveal_duration'] }};
        --reveal-offset: {{ $t['landing']['reveal_offset'] }};
        --float-distance: {{ $t['landing']['float_distance'] }};
        --float-duration: {{ $t['landing']['float_duration'] }};
    }
    @if (empty($t['effects']['enable_animations']))
    :root { --reveal-duration: 0ms; --float-duration: 0ms; --animation-duration: 0ms; }
    @endif
</style>
