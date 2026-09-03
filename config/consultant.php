<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant parsa edit
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'name'       => 'آکادمی جنت',
        'short_name' => 'آکادمی جنت',
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
        'source_permissions' => true,

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
    'theme' => [
        'preset' => 'dark_glass',

        'colors' => [
            'primary'              => '#06B6D4',
            'primary_hover'        => '#0891B2',
            'secondary'            => '#A855F7',
            'secondary_hover'      => '#9333EA',
            'background'           => '#000000',
            'surface'              => '#111111',
            'surface_alt'          => '#1A1A1A',
            'surface_elevated'     => '#202020',
            'text'                 => '#FFFFFF',
            'text_muted'           => '#9CA3AF',
            'text_subtle'          => '#6B7280',
            'border'               => 'rgba(255,255,255,.08)',
            'border_strong'        => 'rgba(255,255,255,.16)',
            'success'              => '#34D399',
            'info'                 => '#60A5FA',
            'warning'              => '#FBBF24',
            'danger'               => '#F87171',
            'selection_background' => '#06B6D4',
            'selection_text'       => '#000000',
            'primary_soft'         => 'rgba(251,191,36,.10)',
            'secondary_soft'       => 'rgba(168,85,247,.10)',
            'accent_blue'          => '#60A5FA',
            'accent_emerald'       => '#34D399',
            'accent_orange'        => '#FB923C',
            'accent_teal'          => '#2DD4BF',
            'accent_red'           => '#F87171',
            'glass'                => 'rgba(255,255,255,.05)',
            'glass_hover'          => 'rgba(255,255,255,.10)',
            'glass_border'         => 'rgba(255,255,255,.10)',
            'on_primary'           => '#000000',
            'on_surface'           => '#FFFFFF',
        ],

        'typography' => [
            'font_family'    => 'Vazirmatn',
            'body_size'      => '15px',
            'body_weight'    => '400',
            'heading_weight' => '800',
            'letter_spacing' => '0',
            'line_height'    => '1.8',
            'h1_size'        => 'clamp(2.75rem, 6vw, 4.5rem)',
            'h2_size'        => 'clamp(1.875rem, 4vw, 3rem)',
            'h3_size'        => '1.25rem',
            'hero_line_height' => '1.2',
        ],

        'shape' => [
            'radius_sm'      => '10px',
            'radius_md'      => '14px',
            'radius_lg'      => '20px',
            'radius_xl'      => '24px',
            'radius_2xl'     => '32px',
            'button_radius'  => '999px',
            'input_radius'   => '14px',
            'card_radius'    => '24px',
            'sidebar_radius' => '24px',
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

        'spacing' => [
            'section_gap'       => '6rem',
            'container_max'     => '80rem',
            'container_padding' => '1.5rem',
            'grid_gap'          => '2rem',
            'card_padding'      => '2rem',
        ],

        'landing' => [
            'nav_height'       => '4.5rem',
            'hero_min_height'  => '100svh',
            'glow_size'        => '24rem',
            'glow_blur'        => '120px',
            'glow_opacity'     => '.10',
            'reveal_duration'  => '700ms',
            'reveal_offset'    => '40px',
            'float_distance'   => '15px',
            'float_duration'   => '6s',
            'counter_duration' => '2000',
            'stagger_ms'       => '100',
        ],

        'effects' => [
            'shadow'             => '0 25px 60px rgba(0,0,0,.35)',
            'card_shadow'        => '0 15px 40px rgba(0,0,0,.18)',
            'sidebar_shadow'     => '0 25px 60px rgba(0,0,0,.45)',
            'backdrop_blur'      => '20px',
            'glass_opacity'      => '.94',
            'hover_lift'         => '3px',
            'animation_duration' => '180ms',
            'enable_animations'  => true,
        ],

        'gradients' => [
            'brand'       => 'linear-gradient(135deg, #06B6D4 0%, #A855F7 100%)',
            'page_glow_1' => 'rgba(251,191,36,.06)',
            'page_glow_2' => 'rgba(168,85,247,.06)',
        ],

        'assets' => [
            'font_url'         => 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
            'icon_library_url' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        ],

        'presets' => [
            'dark_glass' => [
                'primary'     => '#FBBF24',
                'secondary'   => '#A855F7',
                'background'  => '#000000',
                'surface'     => '#111111',
                'surface_alt' => '#1A1A1A',
            ],
            'midnight_blue' => [
                'primary'     => '#60A5FA',
                'secondary'   => '#818CF8',
                'background'  => '#07111F',
                'surface'     => '#0E1B2B',
                'surface_alt' => '#14263B',
            ],
            'emerald_academy' => [
                'primary'     => '#34D399',
                'secondary'   => '#2DD4BF',
                'background'  => '#06130F',
                'surface'     => '#0C211A',
                'surface_alt' => '#123328',
            ],
            'light_minimal' => [
                'primary'     => '#2563EB',
                'secondary'   => '#7C3AED',
                'background'  => '#F6F7FB',
                'surface'     => '#FFFFFF',
                'surface_alt' => '#F0F2F7',
            ],
            'rose_modern' => [
                'primary'     => '#FB7185',
                'secondary'   => '#C084FC',
                'background'  => '#16090E',
                'surface'     => '#251017',
                'surface_alt' => '#351520',
            ],
        ],
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
                'title_line1'   => 'آینده یادگیری با',
                'title_line2'   => 'آکادمی جنت',   // rendered with gradient text
                'gradient_text' => true,
                'subtitle'      => 'پلتفرم یکپارچه آموزش هوشمند. مسیر تحصیلی خود را با برنامه‌ریزی اختصاصی، تحلیل‌های دقیق و سیستم مدیریت یادگیری مدرن متحول کنید.',
                'text_align'    => 'start',        // start | center | end (logical, RTL-safe)
                'text_side'     => 'start',        // which side the text column sits on
                'gradient_dir'  => 'to-l',         // headline gradient direction
                'mockup'        => true,           // floating dashboard illustration
                'buttons' => [
                    ['label' => 'شروع رایگان',    'href' => '#',         'style' => 'solid', 'visible' => true],
                    ['label' => 'مشاهده امکانات', 'href' => '#services', 'style' => 'ghost', 'visible' => true],
                ],
            ],

            'advisor' => [
                'id'         => 'about',
                'image'      => 'images/advisor.jpg',
                'image_alt'  => 'محمدرضا جنت‌فریدونی',
                'image_side' => 'start',           // flip to 'end' to mirror the section
                'image_size' => '18rem',
                'spin_rings' => true,
                'grayscale'  => true,
                'badge'      => ['label' => 'مشاوره فعال', 'visible' => true],
                'eyebrow'    => ['icon' => 'fa-solid fa-certificate', 'label' => 'مدیریت و مشاوره ارشد'],
                'name'       => 'محمدرضا جنت‌فریدونی',
                'tagline'    => 'طراح مسیر موفقیت تحصیلی شما',
                'tagline_color' => 'secondary',    // primary | secondary | any --c-* token
                'bio'        => 'ترکیب سال‌ها تجربه در زمینه مشاوره تحصیلی با قدرت تحلیل هوش مصنوعی، به ما این امکان را می‌دهد که برای هر دانش‌آموز یک نقشه راه اختصاصی طراحی کنیم. من اینجا هستم تا در کنار پلتفرم هوشمند آکادمی جنت، مسیر شما را تا رسیدن به قله‌های موفقیت هموار کنم و در تمام چالش‌های تحصیلی راهنمای شما باشم.',
                'stats' => [
                    ['value' => '۴+',   'label' => 'سال تجربه'],
                    ['value' => '۱۰۰+', 'label' => 'دانش‌آموز موفق'],
                    ['value' => '۱۰۰٪', 'label' => 'پشتیبانی اختصاصی'],
                ],
                'buttons' => [
                    ['label' => 'رزرو وقت مشاوره',  'href' => '#', 'style' => 'primary', 'visible' => true],
                    ['label' => 'مشاهده رزومه کامل', 'href' => '#', 'style' => 'ghost',   'visible' => true],
                ],
            ],

            'ecosystem' => [
                'heading'   => 'اکوسیستم هوشمند آموزش',
                'text'      => 'ما با ترکیب الگوریتم‌های پیشرفته هوش مصنوعی و متدهای نوین یادگیری، محیطی را فراهم کرده‌ایم که در آن هر دانش‌آموز بر اساس نقاط قوت و ضعف خود، مسیر تحصیلی شخصی‌سازی شده‌ای را طی می‌کند.',
                'text_side' => 'end',              // 'end' keeps the original layout
                'visual'    => true,               // decorative orbiting-rings panel
                'items' => [
                    ['icon' => '✦', 'accent' => 'primary',   'label' => 'مشاوره تحصیلی مبتنی بر داده'],
                    ['icon' => '✦', 'accent' => 'secondary', 'label' => 'محیط یادگیری شخصی‌سازی شده'],
                    ['icon' => '✦', 'accent' => 'primary',   'label' => 'آماده‌سازی برای آینده یادگیری'],
                ],
            ],

            'services' => [
                'id'         => 'services',
                'heading'    => 'خدمات ممتاز',
                'subheading' => 'ابزارهای حرفه‌ای و یکپارچه برای رسیدن به بالاترین سطح عملکرد تحصیلی.',
                'columns'    => 3,
                'items' => [
                    ['icon' => 'fa-solid fa-bolt',         'accent' => 'primary',   'title' => 'مشاوره هوشمند',      'text' => 'تحلیل لحظه‌ای وضعیت تحصیلی و ارائه پیشنهادات هوشمندانه برای بهبود راندمان.'],
                    ['icon' => 'fa-solid fa-calendar-days', 'accent' => 'secondary', 'title' => 'برنامه‌ریزی شخصی',  'text' => 'تولید برنامه مطالعاتی دینامیک بر اساس اهداف، زمان خالی و سرعت یادگیری شما.'],
                    ['icon' => 'fa-solid fa-chart-line',    'accent' => 'accent-blue',   'title' => 'تحلیل عملکرد',        'text' => 'نمودارهای پیشرفته و داشبورد مدیریتی برای رصد دقیق پیشرفت تحصیلی.'],
                    ['icon' => 'fa-solid fa-circle-check',  'accent' => 'accent-emerald', 'title' => 'آزمون‌های آزمایشی', 'text' => 'بانک سوالات استاندارد همراه با سیستم برگزاری آزمون مشابه شرایط واقعی.'],
                    ['icon' => 'fa-solid fa-box-archive',   'accent' => 'accent-orange',  'title' => 'مدیریت یادگیری',      'text' => 'دسترسی به محتوای آموزشی، ویدیوها و جزوات در یک پلتفرم متمرکز.'],
                    ['icon' => 'fa-solid fa-robot',         'accent' => 'primary',   'title' => 'دستیار هوش مصنوعی', 'text' => 'پاسخگویی ۲۴ ساعته به سوالات درسی و رفع اشکالات توسط دستیار اختصاصی.'],
                ],
            ],

            'stats' => [
                'columns' => 4,
                'items' => [
                    ['value' => 14,   'suffix' => '+', 'label' => 'دانش‌آموز فعال',   'gradient' => false, 'visible' => true],
                    ['value' => 91,   'suffix' => '٪', 'label' => 'رضایت و موفقیت',   'gradient' => true,  'visible' => true],
                    ['value' => 2000, 'suffix' => '+', 'label' => 'ساعت مشاوره',      'gradient' => false, 'visible' => true],
                    ['value' => 800,  'suffix' => '+', 'label' => 'آزمون برگزار شده', 'gradient' => false, 'visible' => true],
                ],
            ],

            'testimonials' => [
                'id'         => 'testimonials',
                'heading'    => 'پیشگامان موفقیت',
                'subheading' => 'داستان دانش‌آموزانی که با پلتفرم ما مرزهای پیشرفت را جابجا کردند.',
                'items' => [
                    ['initials' => 'ع.ر', 'name' => 'علی رضایی', 'result' => 'رتبه ۱۵۲ کنکور ریاضی',
                     'from' => 'accent-blue', 'to' => 'secondary',
                     'text' => '«دستیار هوش مصنوعی و تحلیل‌های دقیق نموداری به من کمک کرد تا نقاط ضعفم را در دروس اختصاصی پیدا کنم. برنامه‌ریزی‌ها کاملا منطبق بر توانایی من بود.»', 'visible' => true],
                    ['initials' => 'س.م', 'name' => 'سارا محمدی', 'result' => 'قبولی پزشکی تهران',
                     'from' => 'primary', 'to' => 'accent-orange',
                     'text' => '«بانک سوالات و آزمون‌های شبیه‌ساز کاملا مشابه کنکور بودند. استرس من در جلسه اصلی به خاطر تمرین در این پلتفرم بسیار کم بود.»', 'visible' => true],
                    ['initials' => 'ا.ک', 'name' => 'امیرحسین کریمی', 'result' => 'رتبه ۸۷ کنکور انسانی',
                     'from' => 'accent-emerald', 'to' => 'accent-teal',
                     'text' => '«لذت‌بخش‌ترین قسمت برای من رابط کاربری مینیمال و بدون حواس‌پرتی پلتفرم بود. همه چیز سر جای خودش قرار داشت و سرعت پیشرفتم دو برابر شد.»', 'visible' => true],
                ],
            ],

            'blog' => [
                'id'         => 'blog',
                'heading'    => 'آخرین مقالات',
                'subheading' => 'تازه‌ترین متدهای یادگیری و تکنولوژی آموزشی.',
                'see_all'    => ['label' => 'مشاهده همه', 'href' => '#', 'visible' => true],
                'columns'    => 3,
                'items' => [
                    ['image' => 'images/blog-pics/blog-pic1.png', 'from' => 'primary', 'to' => 'secondary',
                     'title' => 'چگونه هوش مصنوعی سبک یادگیری را تغییر می‌دهد؟',
                     'excerpt' => 'بررسی جامع تاثیر الگوریتم‌های هوش مصنوعی بر شخصی‌سازی آموزش و افزایش بازدهی دانش‌آموزان در قرن جدید.', 'url' => '#', 'visible' => true],
                    ['image' => 'images/blog-pics/blog-pic2.png', 'from' => 'accent-blue', 'to' => 'accent-teal',
                     'title' => 'مدیریت زمان در سال کنکور',
                     'excerpt' => 'تکنیک‌های اثبات‌شده برای مدیریت زمان، جلوگیری از فرسودگی ذهنی و تنظیم یک برنامه مطالعاتی متعادل.', 'url' => '#', 'visible' => true],
                    ['image' => 'images/blog-pics/blog-pic3.png', 'from' => 'accent-orange', 'to' => 'accent-red',
                     'title' => 'تحلیل کارنامه: قدم اول پیشرفت',
                     'excerpt' => 'چگونه داده‌های کارنامه آزمون آزمایشی را تفسیر کنیم و از آن‌ها برای تنظیم استراتژی ماه آینده استفاده کنیم.', 'url' => '#', 'visible' => true],
                ],
            ],

            'cta' => [
                'heading' => 'آماده‌ی شروع یک مسیر حرفه‌ای هستید؟',
                'text'    => 'همین حالا به جمع هزاران دانش‌آموز موفق ما بپیوندید و یادگیری را به سطح جدیدی ارتقا دهید.',
                'buttons' => [
                    ['label' => 'ثبت‌نام در پلتفرم',   'route' => 'login', 'style' => 'solid', 'visible' => true],
                    ['label' => 'ورود به حساب کاربری', 'route' => 'login', 'style' => 'ghost', 'visible' => true],
                ],
            ],
        ],

        'footer' => [
            'enabled'        => true,
            'show_logo_mark' => true,
            'blurb'          => 'ما آینده آموزش را با ترکیب هوش مصنوعی، طراحی انسان‌محور و تکنولوژی‌های روز دنیا می‌سازیم. یادگیری هوشمند، حق همه است.',
            'social' => [
                ['icon' => 'fa-brands fa-x-twitter', 'label' => 'X',         'url' => '#', 'visible' => true],
                ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => '#', 'visible' => true],
                ['icon' => 'fa-brands fa-telegram',  'label' => 'Telegram',  'url' => '#', 'visible' => true],
            ],
            'columns' => [
                ['title' => 'دسترسی سریع', 'links' => [
                    ['label' => 'درباره ما',     'href' => '#about'],
                    ['label' => 'خدمات سیستم',   'href' => '#services'],
                    ['label' => 'داستان موفقیت', 'href' => '#testimonials'],
                    ['label' => 'وبلاگ آموزشی',  'href' => '#blog'],
                ]],
                ['title' => 'پشتیبانی', 'links' => [
                    ['label' => 'سوالات متداول', 'href' => '#'],
                    ['label' => 'حریم خصوصی',    'href' => '#'],
                    ['label' => 'شرایط استفاده', 'href' => '#'],
                    ['label' => 'تماس با ما',    'href' => '#'],
                ]],
            ],
            'copyright' => 'تمامی حقوق برای پلتفرم :name محفوظ است. © :year',
        ],

        'login' => [
            'title'          => 'ورود',
            'subtitle'       => 'وارد حساب خود شوید',
            'email_label'    => 'ایمیل',
            'password_label' => 'رمز عبور',
            'remember_label' => 'مرا به خاطر بسپار',
            'submit_label'   => 'ورود',
            'student_link'   => ['label' => 'ورود دانش‌آموز', 'route' => 'student.login', 'visible' => true],
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
            'consultant_link' => ['label' => 'ورود مشاور', 'route' => 'login', 'visible' => true],
            'back_link'      => ['label' => 'بازگشت به سایت', 'route' => 'home', 'visible' => true],
            'card'           => ['max_width' => '26rem', 'glass' => true],
        ],
    ],

];
