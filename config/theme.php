<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant identity — PLATFORM DEFAULTS
    |--------------------------------------------------------------------------
    |
    | Nothing here is tenant-specific at runtime: config/tenants/{slug}.php
    | overrides it per domain. These values only ever show through when no
    | tenant is resolved (console, queue workers, `php artisan serve` on
    | localhost before a fallback domain is configured).
    |
    */
    'tenant' => [
        'name'       => 'Sapienstech',
        'short_name' => 'Sapienstech',
        'role_label' => 'پنل مدیریت',
        'page_title' => 'داشبورد مشاور',
        'logo'       => null,
        'favicon'    => null,
        'locale'     => 'fa',
        'direction'  => 'rtl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Switches
    |--------------------------------------------------------------------------
    |
    | These switches control which features are visible and accessible in the
    | consultant dashboard. Disabling a feature here blocks its routes via
    | the consultant.feature middleware.
    |
    | Multi-Tenant Security Note: These flags ensure that tenants only see
    | and can access the modules they are authorized to use.
    |
    */
    'features' => [

        /*
        |--------------------------------------------------------------------------
        | Core & Dashboard (top navigation: Dashboard | Blog | Direct Chat)
        |--------------------------------------------------------------------------
        */
        'dashboard' => true,
        'blog_management' => true,
        'direct_chat' => true,

        /*
        |--------------------------------------------------------------------------
        | Student Workspace (per-student destinations reached from the
        | dashboard's Actions menu)
        |--------------------------------------------------------------------------
        */
        'student_profile'    => true,
        'report_cards'       => true,
        'student_exams'      => true,
        'student_schedule'   => true,

        /*
        |--------------------------------------------------------------------------
        | Legacy top-level placeholders (no longer linked from navigation,
        | kept for backward compatibility only)
        |--------------------------------------------------------------------------
        */
        'quiz_management'     => true,
        'question_management' => true,
        'book_access'         => true,
        'create_post_action'  => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    */
    /*
    |--------------------------------------------------------------------------
    | Theme — PLATFORM BASELINE
    |--------------------------------------------------------------------------
    |
    | A `null` value is not a bug, it is an instruction: "derive this from the
    | primitives". App\Support\ThemeTokens fills every null via color-mix()
    | against primary/secondary/background/text, which is why a tenant file can
    | set six colors and still get a complete, coherent palette.
    |
    | Set a value explicitly only when you want to override the derivation.
    |
    | Layer order (App\Support\SiteConfig): this baseline -> archetypes/{name}
    | -> tenants/{slug} -> website_configs.layout_config. Later wins.
    |
    */
    'theme' => [

        // The visual identity bundle. See config/archetypes/*.php.
        'archetype' => 'aurora_glass',

        'colors' => [
            // ---- primitives: an archetype is expected to set these --------
            'primary'              => '#06B6D4',
            'secondary'            => '#A855F7',
            'background'           => '#000000',
            'surface'              => '#111111',
            'surface_alt'          => '#1A1A1A',
            'text'                 => '#FFFFFF',

            // ---- semantic: rarely archetype-specific, override per tenant --
            'success'              => '#34D399',
            'info'                 => '#60A5FA',
            'warning'              => '#FBBF24',
            'danger'               => '#F87171',

            // ---- accent palette used by items[].accent / from / to --------
            'accent_blue'          => '#60A5FA',
            'accent_emerald'       => '#34D399',
            'accent_orange'        => '#FB923C',
            'accent_teal'          => '#2DD4BF',
            'accent_red'           => '#F87171',

            // ---- derived: null means "compute from the primitives" --------
            'primary_hover'        => null,
            'secondary_hover'      => null,
            'primary_soft'         => null,
            'secondary_soft'       => null,
            'text_muted'           => null,
            'text_subtle'          => null,
            'surface_elevated'     => null,
            'border'               => null,
            'border_strong'        => null,
            'glass'                => null,
            'glass_hover'          => null,
            'glass_border'         => null,
            'on_primary'           => null,
            'on_surface'           => null,
            'selection_background' => null,
            'selection_text'       => null,
        ],

        // How a surface is *made*, not what colour it is. Drives the blur,
        // transparency and grain shared by nav, cards and the hero mockup.
        // material: glass | acrylic | matte | paper | flat
        'surface' => [
            'material' => null,
            'blur'     => null,
            'opacity'  => null,
            'noise'    => null,
            'specular' => null,
        ],

        // Elevation model. elevation: flat | ambient | soft | medium | heavy |
        // neon | brutal. shadow_tint mixes the brand background into shadows
        // instead of using generic black.
        'depth' => [
            'elevation'   => 'soft',
            'shadow_tint' => true,
        ],

        // One multiplier for the whole page's proportions.
        // density: compact | normal | spacious   container_width: narrow |
        // content | wide | full
        'scale' => [
            'density'         => 'normal',
            'container_width' => 'content',
            'section_rhythm'  => '6rem',
        ],

        // Motion identity. reveal: fade-up | fade-down | fade-left | fade-right
        // | zoom | blur-in | line-mask | none   parallax: none | subtle | strong
        'motion' => [
            'reveal'         => 'fade-up',
            'stagger'        => 'sequential',
            'easing'         => null,
            'duration_scale' => '1',
            'parallax'       => 'none',
            'tilt'           => false,
            'magnetic'       => false,
            'hover'          => 'lift',
        ],

        // Page and section ground. mode: glow | flat | gradient | mesh | grid
        // | dots | noise | beams   section_alternation: none | tint | surface-alt | rule
        'background' => [
            'mode'                => 'glow',
            'gradient_angle'      => '180deg',
            'section_alternation' => 'none',
            'noise'               => '0',
            'grid_size'           => '48px',
            // Replaces the blobs hero/advisor/ecosystem/cta used to hand-place.
            // Each: ['at' => 'top-start', 'color' => 'primary', 'size' => '24rem', 'opacity' => '.10']
            'glow_blobs'          => [],
        ],

        // Ornament. section_divider: none | line | slant | wave | gradient-band
        // heading_rule: none | short-bar | full-line | number | eyebrow-pill
        'decoration' => [
            'section_divider' => 'none',
            'heading_rule'    => 'none',
            'heading_align'   => 'center',
            'accent_shapes'   => 'none',
            'quote_mark'      => 'none',
            'icon_backdrop'   => 'soft-square',
        ],

        // Button anatomy, shared by every CTA on the public site.
        // variant: solid | outline | soft | ghost | gradient | glass | brutal | underline
        'buttons' => [
            'variant'   => 'solid',
            'size'      => 'md',
            'weight'    => '600',
            'transform' => 'none',
            'icon'      => 'none',
            'hover'     => 'lift',
        ],

        // Logo lockup. variant: mark | wordmark | mark+word | stacked
        'brand' => [
            'src'      => null,
            'height'   => '2rem',
            'variant'  => 'mark+word',
            'position' => 'start',
        ],

        // numerals: auto | fa | en    calendar: gregorian | jalali
        'i18n' => [
            'numerals'    => 'auto',
            'calendar'    => 'gregorian',
            'date_format' => 'Y-m-d',
        ],

        'accessibility' => [
            'contrast'      => 'normal',   // normal | high
            'focus_ring'    => 'outline',  // outline | glow | underline | none
            'reduce_motion' => 'respect',  // respect | force-off | force-on
        ],

        // Extra colour schemes the light/dark toggle can switch to. Replaces the
        // palette that used to be hardcoded in partials/color-scheme.blade.php,
        // so a tenant's light mode is now theirs. Only the tokens listed here
        // change; everything derived recomputes from them.
        'schemes' => [
            'light' => [
                'background'       => '#F6F7FB',
                'surface'          => '#FFFFFF',
                'surface_alt'      => '#F0F2F7',
                'surface_elevated' => '#FFFFFF',
                'text'             => '#111827',
            ],
        ],

        // Escape hatch for one-off requests. Appended verbatim to :root.
        // Admin-only: never expose this field to a tenant-facing UI.
        'custom' => [
            'css' => null,
        ],

        'typography' => [
            // One font family for everything was the single biggest sameness
            // driver. An archetype sets font_family + font_heading together.
            'font_family'      => 'Vazirmatn',
            'font_heading'     => null,
            'font_accent'      => null,
            'font_mono'        => null,
            'body_size'        => '15px',
            'body_weight'      => '400',
            'heading_weight'   => '800',
            'heading_transform'=> null,
            'heading_letter_spacing' => null,
            'letter_spacing'   => '0',
            'line_height'      => '1.8',
            'measure'          => null,
            'h1_size'          => 'clamp(2.75rem, 6vw, 4.5rem)',
            'h2_size'          => 'clamp(1.875rem, 4vw, 3rem)',
            'h3_size'          => '1.25rem',
            'hero_line_height' => '1.2',
        ],

        // Nulls here so `scale.radius_scale` in an archetype can retune the
        // whole group at once; ThemeTokens falls back to the values it used to
        // have when no scale is named.
        'shape' => [
            'radius_scale'     => null,   // sharp | slight | soft | round | pill
            'radius_sm'        => null,
            'radius_md'        => null,
            'radius_lg'        => null,
            'radius_xl'        => null,
            'radius_2xl'       => null,
            'button_radius'    => null,
            'input_radius'     => null,
            'card_radius'      => null,
            'sidebar_radius'   => null,
            'border_width'     => '1px',
        ],

        'layout' => [
            'sidebar_width'     => '240px',
            'content_max_width' => '1280px',
            'content_padding'   => '32px',
            'sidebar_top'       => '36px',
            'sidebar_bottom'    => '48px',
            'sidebar_offset'    => '36px',
            'card_gap'          => '24px',
            'topnav_height'     => '76px',
        ],

        // Driven by `scale.density` / `scale.container_width` / section_rhythm.
        'spacing' => [
            'section_gap'       => null,
            'container_max'     => null,
            'container_padding' => null,
            'grid_gap'          => null,
            'card_padding'      => null,
        ],

        'landing' => [
            'nav_height'       => '4.5rem',
            'hero_min_height'  => '100svh',
            'glow_size'        => '24rem',
            'glow_blur'        => '120px',
            'glow_opacity'     => '.10',
            'reveal_offset'    => '40px',
            'float_distance'   => '15px',
            'float_duration'   => '6s',
            'counter_duration' => '2000',
            // Scaled by motion.duration_scale.
            'reveal_duration'  => null,
            'stagger_ms'       => null,
        ],

        // Shadows come from `depth`; blur/opacity from `surface`. Left null so
        // those two groups are the only place to change them.
        'effects' => [
            'shadow'             => null,
            'card_shadow'        => null,
            'sidebar_shadow'     => null,
            'backdrop_blur'      => null,
            'glass_opacity'      => null,
            'hover_lift'         => '3px',
            'animation_duration' => '180ms',
            'enable_animations'  => true,
        ],

        'gradients' => [
            // Was hardcoded to the old cyan/purple palette and quietly drifted
            // out of sync with `colors`. Now derived from primary + secondary.
            'brand'       => null,
            'page_glow_1' => null,
            'page_glow_2' => null,
        ],

        'assets' => [
            'font_url'         => 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
            'icon_library_url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        ],

        // The old inline `presets` array lived here and was dead code: nothing
        // ever merged it into `colors`. Full identity bundles now live in
        // config/archetypes/*.php, applied by App\Support\SiteConfig.
    ],

    /*
    |--------------------------------------------------------------------------
    | Labels
    |--------------------------------------------------------------------------
    */
    'labels' => [
        // Top navigation
        'dashboard'       => 'داشبورد',
        'blog_management' => 'وبلاگ',
        'direct_chat'     => 'گفتگوی مستقیم',

        // Dashboard
        'dashboard_heading' => 'داشبورد مشاور',
        'welcome_prefix'    => 'خوش آمدید،',

        // Student list
        'student_list' => 'لیست دانش‌آموزان شما',

        // Misc
        'logout' => 'خروج از حساب',
    ],

    /*
    |--------------------------------------------------------------------------
    | Public Site (landing page, nav, footer, login)
    |--------------------------------------------------------------------------
    |
    | Everything the visitor-facing pages show. Colors/sizes are NOT repeated
    | here — they all flow from the `theme` block through CSS variables.
    | `landing.sections` controls which sections render and in which order.
    |
    */
    'public' => [

        'nav' => [
            'enabled'        => true,
            'style'          => 'solid',   // solid | glass | transparent
            'position'       => 'top',     // top (left-rail and floating-pill are future variants)
            'sticky'         => true,
            'show_logo_mark' => true,
            'links'          => [
                ['label' => 'درباره ما',        'href' => '#about',        'visible' => true],
                ['label' => 'خدمات',            'href' => '#services',     'visible' => true],
                ['label' => 'دانش‌آموزان موفق', 'href' => '#testimonials', 'visible' => true],
                ['label' => 'وبلاگ',            'href' => '#blog',         'visible' => true],
            ],
            'cta' => [
                'label'   => 'ورود | ثبت‌نام',
                'route'   => 'login',
                'visible' => true,
            ],
        ],

        'landing' => [
            'meta' => [
                'title'       => 'پلتفرم آموزش هوشمند',
                'description' => 'سیستم مدیریت یادگیری و مشاوره تحصیلی مبتنی بر هوش مصنوعی',
            ],

            // ORDER + VISIBILITY of every section. Reorder freely; remove to hide.
            'sections' => ['hero', 'advisor', 'ecosystem', 'services', 'stats', 'testimonials', 'blog', 'cta'],

            'animations' => [
                'reveal'   => true,   // fade-in on scroll
                'float'    => true,   // floating hero cards
                'counters' => true,   // animated stat counters
            ],

            'hero' => [
                // Placeholder copy. A tenant overrides these in
                // config/tenants/{slug}.php — see tenant-one.php.
                'title_line1'   => 'آینده یادگیری با',
                'title_line2'   => 'نام برند شما',   // rendered with gradient text
                'gradient_text' => true,
                'subtitle'      => 'یک پلتفرم یکپارچه آموزش هوشمند. مسیر تحصیلی خود را با برنامه‌ریزی اختصاصی و تحلیل‌های دقیق متحول کنید.',
                'text_align'    => 'start',        // start | center | end (logical, RTL-safe)
                'text_side'     => 'start',        // which side the text column sits on
                'gradient_dir'  => 'to-l',         // headline gradient direction
                'mockup'        => true,           // floating dashboard illustration
                // Structure levers consumed by sections/_dispatch.blade.php.
                'layout'        => 'default',      // default | centered-stack | oversized-type
                'media'         => 'mockup',       // mockup | photo | none
                'image'         => null,
                'image_alt'     => null,
                'eyebrow'       => null,
                'buttons' => [
                    ['label' => 'شروع رایگان',    'href' => '#',         'style' => 'solid', 'visible' => true],
                    ['label' => 'مشاهده امکانات', 'href' => '#services', 'style' => 'ghost', 'visible' => true],
                ],
            ],

            'advisor' => [
                'variant'    => 'default',          // default | quote-first
                // The whole block is person-specific; a tenant supplies its own
                // advisor rather than restating these keys one by one.
                'id'         => 'about',
                'image'      => 'images/advisor.jpg',
                'image_alt'  => 'تصویر مشاور',
                'image_side' => 'start',           // flip to 'end' to mirror the section
                'image_size' => '18rem',
                'spin_rings' => true,
                'grayscale'  => true,
                'badge'      => ['label' => 'مشاوره فعال', 'visible' => true],
                'eyebrow'    => ['icon' => 'fa-solid fa-certificate', 'label' => 'مدیریت و مشاوره ارشد'],
                'name'       => 'نام مشاور',
                'tagline'    => 'طراح مسیر موفقیت تحصیلی شما',
                'tagline_color' => 'secondary',    // primary | secondary | any --c-* token
                'bio'        => 'سال‌ها تجربه در زمینه مشاوره تحصیلی همراه با تحلیل هوش مصنوعی، به ما امکان می‌دهد برای هر دانش‌آموز یک نقشه راه اختصاصی طراحی کنیم.',
                'stats' => [
                    ['value' => '+۰',   'label' => 'سال تجربه'],
                    ['value' => '+۰',   'label' => 'دانش‌آموز موفق'],
                    ['value' => '۱۰۰٪', 'label' => 'پشتیبانی اختصاصی'],
                ],
                'buttons' => [
                    ['label' => 'رزرو وقت مشاوره',  'href' => '#', 'style' => 'primary', 'visible' => true],
                    ['label' => 'مشاهده رزومه کامل', 'href' => '#', 'style' => 'ghost',   'visible' => true],
                ],
            ],

            'ecosystem' => [
                'variant'   => 'default',
                'id'        => 'ecosystem',
                'heading'   => 'اکوسیستم هوشمند آموزش',
                'text'      => 'با ترکیب الگوریتم‌های هوش مصنوعی و متدهای نوین یادگیری، محیطی ساخته‌ایم که هر دانش‌آموز مسیر شخصی‌سازی‌شده خودش را طی می‌کند.',
                'text_side' => 'end',              // 'end' keeps the original layout
                'visual'    => true,               // decorative orbiting-rings panel
                'items' => [
                    ['icon' => '✦', 'accent' => 'primary',   'label' => 'مشاوره تحصیلی مبتنی بر داده'],
                    ['icon' => '✦', 'accent' => 'secondary', 'label' => 'محیط یادگیری شخصی‌سازی شده'],
                    ['icon' => '✦', 'accent' => 'primary',   'label' => 'آماده‌سازی برای آینده یادگیری'],
                ],
            ],

            'services' => [
                'variant'    => 'default',           // default | numbered-list
                'id'         => 'services',
                'heading'    => 'خدمات ممتاز',
                'subheading' => 'ابزارهای حرفه‌ای و یکپارچه برای رسیدن به بالاترین سطح عملکرد تحصیلی.',
                'columns'    => 3,
                // One placeholder card; tenants supply their own list.
                'items' => [
                    ['icon' => 'fa-solid fa-bolt', 'accent' => 'primary', 'title' => 'عنوان خدمت', 'text' => 'توضیح کوتاه درباره این خدمت و مزیت آن برای دانش‌آموز.'],
                ],
            ],

            'stats' => [
                'variant' => 'default',              // default | inline-divider | band
                'id'      => 'stats',
                'columns' => 4,
                'items' => [
                    ['value' => 0, 'suffix' => '+', 'label' => 'دانش‌آموز فعال', 'gradient' => false, 'visible' => true],
                ],
            ],

            'testimonials' => [
                'variant'    => 'default',           // default | single-featured | marquee
                'id'         => 'testimonials',
                'heading'    => 'پیشگامان موفقیت',
                'subheading' => 'داستان دانش‌آموزانی که با پلتفرم ما مرزهای پیشرفت را جابجا کردند.',
                'items' => [
                    ['initials' => 'ن.م', 'name' => 'نام دانش‌آموز', 'result' => 'نتیجه کسب‌شده',
                     'from' => 'primary', 'to' => 'secondary',
                     'text' => '«نقل‌قول دانش‌آموز از تجربه‌اش در استفاده از پلتفرم.»', 'visible' => true],
                ],
            ],

            'blog' => [
                'variant'    => 'default',           // default | list
                'id'         => 'blog',
                'heading'    => 'آخرین مقالات',
                'subheading' => 'تازه‌ترین متدهای یادگیری و تکنولوژی آموزشی.',
                'see_all'    => ['label' => 'مشاهده همه', 'href' => '#', 'visible' => true],
                'columns'    => 3,
                'items' => [
                    ['image' => 'images/blog-placeholder.png', 'from' => 'primary', 'to' => 'secondary',
                     'title' => 'عنوان مقاله',
                     'excerpt' => 'چکیده‌ای کوتاه از آنچه این مقاله پوشش می‌دهد.', 'url' => '#', 'visible' => true],
                ],
            ],

            'cta' => [
                'variant' => 'default',              // default | boxed-card | split
                'id'      => 'cta',
                'heading' => 'آماده‌ی شروع یک مسیر حرفه‌ای هستید؟',
                'text'    => 'همین حالا ثبت‌نام کنید و یادگیری را به سطح جدیدی ارتقا دهید.',
                'buttons' => [
                    ['label' => 'ثبت‌نام در پلتفرم',   'route' => 'login', 'style' => 'solid', 'visible' => true],
                    ['label' => 'ورود به حساب کاربری', 'route' => 'login', 'style' => 'ghost', 'visible' => true],
                ],
            ],

            /*
            |----------------------------------------------------------------------
            | Optional sections
            |----------------------------------------------------------------------
            |
            | Not in the default `sections` list. An archetype (or a tenant) opts in
            | by adding the name to `sections` and filling these keys. Content is
            | tenant-owned, so the defaults are deliberately empty.
            |
            */

            // Trust bar: partner/school marks.
            'logos' => [
                'id'      => 'logos',
                'heading' => null,
                'variant' => 'default',
                'items'   => [],   // ['name' => '', 'image' => null, 'visible' => true]
            ],

            // Numbered how-it-works timeline.
            'process' => [
                'id'         => 'process',
                'heading'    => 'مسیر همکاری',
                'subheading' => null,
                'variant'    => 'default',
                'items'      => [],   // ['title' => '', 'text' => '', 'visible' => true]
            ],

            'faq' => [
                'id'         => 'faq',
                'heading'    => 'سوالات متداول',
                'subheading' => null,
                'variant'    => 'default',
                'items'      => [],   // ['question' => '', 'answer' => '', 'visible' => true]
            ],

            'comparison' => [
                'id'         => 'comparison',
                'heading'    => 'مقایسه',
                'subheading' => null,
                'variant'    => 'default',
                'columns'    => ['', 'روش سنتی', 'پلتفرم ما'],
                'items'      => [],   // ['label' => '', 'cells' => [true, false, 'متن'], 'visible' => true]
            ],
        ],

        'footer' => [
            'enabled'        => true,
            'show_logo_mark' => true,
            // Brand voice, links and social handles are tenant-owned; see
            // config/tenants/tenant-one.php for a worked example.
            'blurb'          => 'آموزش هوشمند، حق همه است.',
            'social' => [
                ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => '#', 'visible' => true],
                ['icon' => 'fa-brands fa-telegram',  'label' => 'Telegram',  'url' => '#', 'visible' => true],
            ],
            'columns' => [
                ['title' => 'دسترسی سریع', 'links' => [
                    ['label' => 'درباره ما',   'href' => '#about'],
                    ['label' => 'خدمات',       'href' => '#services'],
                ]],
                ['title' => 'پشتیبانی', 'links' => [
                    ['label' => 'سوالات متداول', 'href' => '#'],
                    ['label' => 'تماس با ما',    'href' => '#'],
                ]],
            ],
            'copyright' => 'تمامی حقوق برای :name محفوظ است. © :year',
        ],

        'login' => [
            'title'          => 'ورود',
            'subtitle'       => 'وارد حساب خود شوید',
            'email_label'    => 'ایمیل',
            'password_label' => 'رمز عبور',
            'remember_label' => 'مرا به خاطر بسپار',
            'submit_label'   => 'ورود',
            'tabs'           => ['consultant' => 'مشاور', 'student' => 'دانش‌آموز'],
            'back_link'      => ['label' => 'بازگشت به سایت', 'route' => 'home', 'visible' => true],
            'card'           => ['max_width' => '26rem', 'glass' => true],
        ],

        'student_login' => [
            'title'          => 'ورود دانش‌آموز',
            'subtitle'       => 'وارد پنل دانش‌آموز شوید',
            'email_label'    => 'ایمیل',
            'password_label' => 'رمز عبور',
            'remember_label' => 'مرا به خاطر بسپار',
            'submit_label'   => 'ورود',
            'back_link'      => ['label' => 'بازگشت به سایت', 'route' => 'home', 'visible' => true],
            'card'           => ['max_width' => '26rem', 'glass' => true],
        ],
    ],

];
