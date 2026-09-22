<?php

/*
|--------------------------------------------------------------------------
| Tenant: salam  (domain: salamacademy.test)
|--------------------------------------------------------------------------
|
| آکادمی سلام — a 1-on-1 consultancy for high school (دهم تا دوازدهم),
| not a school. Hierarchy: admin آرزو بهرامی, 3 consultants
| (بهاره نظری، امیرحسین کریمی، سپیده رحیمی), 12 individually-assigned
| students, no classrooms, no class grouping. Teacher panel OFF.
|
| Audience: کنکور-track students and their parents. The promise is personal:
| your own consultant, weekly conversation, exam analysis — never a class.
| Copy below is grounded in the real roster; page copy is marked نمایشی
| where it illustrates the pipeline rather than stating facts.
|
| Identity: brutalist_mono — the third archetype, deliberately the furthest
| from aurora_glass: hard edges, no blur, no shadows, no easing, a visible
| grid, oversized type, numbered lists. The same warm amber/rose identity
| restated as flat blocks on warm charcoal, so the consultancy reads direct
| and no-nonsense: manifesto hero, numbered process, comparison table,
| hard-edged quote cards. Config only; no Blade touched.
|
*/

return [
    'tenant' => [
        'name' => 'آکادمی سلام',
        'short_name' => 'آکادمی سلام',
        'role_label' => 'پنل مشاوره',
        'page_title' => 'داشبورد مشاور',
    ],

    'features' => [
        'dashboard' => true,
        'student_profile' => true,
        'student_schedule' => true,
        'student_exams' => true,
        'report_cards' => true,
        'direct_chat' => true,
        'student_chat' => true,
        'bulk_actions' => true,
        'teacher_panel' => false,
        'teacher_materials' => false,
        'teacher_assignments' => false,
        'teacher_schedule' => false,
        'student_materials' => false,
        'student_assignments' => false,
        'student_timetable' => false,
        'appearance_staff_publish' => false,
    ],

    'theme' => [
        'archetype' => 'brutalist_mono',
        'icons' => ['set' => 'lucide'],

        // The same warm consultancy palette, restated in brutalist language:
        // amber/rose blocks on warm charcoal, hard edges, no blur, no shadow.
        // Text/background contrast stays AA; the brand gradient still derives
        // from primary + secondary.
        'colors' => [
            'primary'     => '#FB923C',
            'secondary'   => '#F43F5E',
            'background'  => '#0C0A09',
            'surface'     => '#1C1917',
            'surface_alt' => '#292524',
            'text'        => '#FFF7ED',
            'link'        => '#FDBA74',
        ],

        'schemes' => [
            'light' => [
                'background'       => '#FFF7ED',
                'surface'          => '#FFFFFF',
                'surface_alt'      => '#FFEDD5',
                'surface_elevated' => '#FFFFFF',
                'text'             => '#431407',
            ],
        ],

        'typography' => [
            'font_family' => 'Vazirmatn, sans-serif',
            'font_heading' => 'Vazirmatn, sans-serif',
            'font_button' => 'Vazirmatn, sans-serif',
            'font_mono' => 'ui-monospace, monospace',
            // Vazirmatn is the only bundled face, so the brutal voice comes
            // from weight 900 (the variable face's Black axis) at the
            // archetype's oversized clamp scale rather than a remote display
            // face. JetBrains Mono falls through to the ui-monospace generic.
            'heading_weight' => '900',
            'body_size' => '16px',
            'heading_letter_spacing' => '0',
            'line_height' => '1.6',
            'measure' => '40rem',
        ],

        // Brutalist motion: state changes are instant. No reveal drift, no
        // easing, no glow, no scroll bar, no tilt — cards answer with colour,
        // not movement (hover invert has no transition by design).
        'motion' => [
            'reveal'          => 'none',
            'stagger'         => 'none',
            'duration_scale'  => '0',
            'easing'          => 'steps(1)',
            'parallax'        => 'none',
            'hover'           => 'invert',
            'text_effect'     => 'none',
            'scroll_progress' => false,
            'tilt'            => false,
            'magnetic'        => false,
        ],

        // Background (grid ground, tint alternation), decoration (slant
        // dividers, numbered heading rules, plus marks), buttons (brutal
        // blocks with hard offset shadows), depth (flat, no shadows), shape
        // (sharp), scale (compact/wide) and the hero structure all come from
        // the brutalist_mono bundle — nothing to restate here.
    ],

    'academics' => [
        'default_group' => 'high_school',
        'subjects' => ['ریاضی', 'فیزیک', 'شیمی', 'زیست‌شناسی', 'ادبیات', 'عربی', 'علوم اجتماعی'],
    ],

    'public' => [
        'nav' => [
            'links_style' => 'plain',
            'cta_style'   => 'solid',
            'links' => [
                ['label' => 'درباره ما',      'href' => '#about',      'visible' => true],
                ['label' => 'مسیر مشاوره',    'href' => '#process',    'visible' => true],
                ['label' => 'چرا فردی؟',      'href' => '#comparison', 'visible' => true],
                ['label' => 'وبلاگ',          'href' => '#blog',       'visible' => true],
            ],
            'cta' => [
                'label'   => 'ورود | ثبت‌نام',
                'route'   => 'login',
                'visible' => true,
            ],
        ],

        'landing' => [
            'meta' => [
                'title'       => 'مشاوره و برنامه‌ریزی کنکور · دهم تا دوازدهم',
                'description' => 'آکادمی سلام؛ مشاوره فردی کنکور با مشاور اختصاصی، برنامه هفتگی و تحلیل آزمون. بدون گروه‌بندی کلاسی.',
                'og_image'    => null,
            ],

            // Consultancy lineup: trust bar, process and comparison do the
            // persuading; marquee voices keep it human; team lives in blocks.
            'sections' => ['hero', 'logos', 'advisor', 'process', 'services', 'comparison', 'stats', 'testimonials', 'faq', 'blocks', 'blog', 'cta'],

            'hero' => [
                'title_line1' => 'یک مسیر، چند همراه متخصص',
                'title_line2' => 'آکادمی سلام',
                'subtitle' => 'مشاوره و برنامه‌ریزی کنکور برای پایه‌های دهم تا دوازدهم؛ هر دانش‌آموز با یک یا چند مشاور اختصاصی، بدون گروه‌بندی کلاسی. اطلاعات این صفحه نمایشی است.',
                // Structure (oversized-type, media none) comes from the
                // brutalist bundle: type IS the visual.
                'buttons' => [
                    ['label' => 'شروع با گفت‌وگوی آشنایی', 'href' => '#cta',      'style' => 'solid', 'icon' => 'sparkle',    'visible' => true],
                    ['label' => 'مشاهده خدمات',            'href' => '#services', 'style' => 'ghost',  'icon' => 'arrow-down', 'visible' => true],
                ],
            ],

            'logos' => [
                'heading' => 'همراه دانش‌آموزان هر سه پایه متوسطه دوم',
                'items' => [
                    ['name' => 'رشته تجربی', 'visible' => true],
                    ['name' => 'رشته ریاضی', 'visible' => true],
                    ['name' => 'رشته انسانی', 'visible' => true],
                ],
            ],

            'advisor' => [
                // quote-first: the statement leads and no portrait renders
                // (image null). The team carries the identity in the blocks
                // strip below, by name.
                'variant' => 'quote-first',
                'id' => 'about',
                'image' => null,
                'badge' => ['label' => 'پذیرش دهم تا دوازدهم', 'visible' => true],
                'eyebrow' => ['icon' => 'fa-solid fa-user-group', 'label' => 'تیم مشاوره، نه کلاس کنکور'],
                'name' => 'آرزو بهرامی',
                'tagline' => 'مدیر تیم مشاوره آکادمی سلام',
                'tagline_color' => 'primary',
                'bio' => 'تیم سه‌نفره سلام با برنامه‌ریزی فردی، تحلیل آزمون و گفت‌وگوی هفتگی همراه دانش‌آموزان رشته‌های تجربی، ریاضی و انسانی است؛ هر دانش‌آموز مشاور اختصاصی خودش را دارد و در ماه‌های جمع‌بندی، مشاور دوم هم به تیم اضافه می‌شود. این معرفی داده نمایشی سامانه است.',
                'stats' => [
                    ['value' => '۱۲', 'label' => 'دانش‌آموز نمونه'],
                    ['value' => '۳',  'label' => 'مشاور اختصاصی'],
                    ['value' => '۷',  'label' => 'گفت‌وگوی هفتگی هر دانش‌آموز'],
                ],
                'buttons' => [
                    ['label' => 'آشنایی با مشاوران', 'href' => '#blocks', 'style' => 'primary', 'icon' => 'arrow', 'visible' => true],
                    ['label' => 'رزرو گفت‌وگو',      'href' => '#cta',    'style' => 'ghost',   'icon' => null,  'visible' => true],
                ],
            ],

            'process' => [
                'heading' => 'مسیر مشاوره در سلام',
                'subheading' => 'چهار قدم از آشنایی تا روز کنکور؛ همه‌چیز حول یک نفر می‌چرخد: تو.',
                'items' => [
                    ['title' => 'گفت‌وگوی شناخت', 'text' => 'پایه، رشته، عادت‌های مطالعه و هدفت را می‌شناسیم؛ بدون تعهد و بدون کلاس.', 'visible' => true],
                    ['title' => 'تخصیص مشاور اختصاصی', 'text' => 'از میان سه مشاور تیم، کسی که به نیازت نزدیک‌تر است همراهت می‌شود؛ در ماه‌های جمع‌بندی، مشاور دوم هم اضافه می‌شود.', 'visible' => true],
                    ['title' => 'برنامه هفتگی و پیگیری', 'text' => 'هر هفته برنامه اختصاصی می‌گیری و در گفت‌وگوی هفتگی، اجرایت را با مشاورت مرور می‌کنی.', 'visible' => true],
                    ['title' => 'تحلیل آزمون و تنظیم مسیر', 'text' => 'نتیجه هر آزمون آزمایشی تحلیل می‌شود و برنامه هفته بعد بر همان اساس بازچینی می‌گردد.', 'visible' => true],
                ],
            ],

            'services' => [
                // Numbered list in two columns: one promise per row, exactly
                // the consultancy pitch.
                'variant' => 'numbered-list',
                'columns' => 2,
                'heading' => 'هر مشاور، یک تخصص',
                'subheading' => 'آنچه در گفت‌وگوی هفتگی و پنل سلام می‌گذرد؛ برگرفته از تخصص واقعی مشاوران تیم.',
                'items' => [
                    ['icon' => 'fa-solid fa-calendar-check', 'accent' => 'accent_orange', 'title' => 'برنامه‌ریزی فردی و متعادل', 'text' => 'برنامه هفتگی متناسب با پایه، رشته و ساعت واقعی مطالعه‌ات؛ نه برنامه آماده کلاسی.', 'visible' => true],
                    ['icon' => 'fa-solid fa-chart-bar', 'accent' => 'accent_rose', 'title' => 'تحلیل آزمون و پیگیری پیشرفت', 'text' => 'هر آزمون آزمایشی موشکافی می‌شود تا نقطه‌ضعف بعدی، برنامه بعدی را بسازد.', 'visible' => true],
                    ['icon' => 'fa-solid fa-location-dot', 'accent' => 'accent_amber', 'title' => 'انتخاب مسیر تحصیلی', 'text' => 'از انتخاب رشته تا تصمیم‌های حساس سال دوازدهم، با دید کسی که مسیر را می‌شناسد.', 'visible' => true],
                    ['icon' => 'fa-solid fa-highlighter', 'accent' => 'accent_violet', 'title' => 'مرور و خلاصه‌نویسی', 'text' => 'مهارت‌هایی که رتبه می‌سازند: مرور اصولی، خلاصه‌نویسی و تست‌زنی هوشمند.', 'visible' => true],
                    ['icon' => 'fa-solid fa-face-smile', 'accent' => 'accent_cyan', 'title' => 'عادت مطالعه و آرامش آزمون', 'text' => 'ساختن عادت‌های پایدار مطالعه و مدیریت اضطراب جلسه آزمون، قدم‌به‌قدم.', 'visible' => true],
                    ['icon' => 'fa-solid fa-comments', 'accent' => 'accent_lime', 'title' => 'گفت‌وگوی هفتگی', 'text' => 'قرار ثابت هر هفته با مشاورت؛ جایی برای سؤال، تنظیم و انگیزه گرفتن.', 'visible' => true],
                ],
            ],

            'comparison' => [
                'heading' => 'چرا مشاوره فردی؟',
                'subheading' => 'سلام را با کلاس کنکور سنتی روبه‌رو بگذار.',
                'columns' => ['', 'کلاس کنکور سنتی', 'آکادمی سلام'],
                'items' => [
                    ['label' => 'برنامه متناسب با هر دانش‌آموز', 'cells' => [false, true], 'visible' => true],
                    ['label' => 'مشاور اختصاصی', 'cells' => [false, true], 'visible' => true],
                    ['label' => 'گفت‌وگوی هفتگی یک‌به‌یک', 'cells' => [false, true], 'visible' => true],
                    ['label' => 'تحلیل فردی هر آزمون', 'cells' => [false, true], 'visible' => true],
                    ['label' => 'گروه‌بندی کلاسی', 'cells' => ['۳۰ نفره و بیشتر', 'ندارد — کاملاً فردی'], 'visible' => true],
                ],
            ],

            'stats' => [
                'variant' => 'band',
                'columns' => 3,
                'items' => [
                    ['value' => 12, 'suffix' => '', 'label' => 'دانش‌آموز نمونه', 'gradient' => false, 'visible' => true],
                    ['value' => 3,  'suffix' => '', 'label' => 'مشاور اختصاصی',  'gradient' => true,  'visible' => true],
                    ['value' => 3,  'suffix' => '', 'label' => 'پایه تحصیلی',    'gradient' => false, 'visible' => true],
                ],
            ],

            'testimonials' => [
                // Hard-edged quote cards instead of the marquee: the brutalist
                // bundle runs with reduce_motion=force-off, which freezes a
                // marquee track mid-page (animation: none).
                'variant' => 'default',
                'heading' => 'از زبان خودشان',
                'subheading' => 'حس دانش‌آموزانی که با مشاور اختصاصی پیش می‌روند. (نقل‌قول‌ها نمایشی‌اند.)',
                'items' => [
                    ['initials' => 'ن.ت', 'name' => 'نگار تهرانی', 'result' => 'دانش‌آموز دوازدهم تجربی',
                     'from' => 'accent_orange', 'to' => 'accent_rose',
                     'text' => '«قبلاً در کلاس گم بودم؛ حالا هر هفته کسی دقیقاً می‌داند کجای مسیرم و قدم بعدی چیست.»', 'visible' => true],
                    ['initials' => 'پ.ر', 'name' => 'پارسا رضایی', 'result' => 'دانش‌آموز یازدهم ریاضی',
                     'from' => 'accent_amber', 'to' => 'accent_orange',
                     'text' => '«تحلیل آزمون‌ها عجیب دقیق است؛ بعد از هر آزمون می‌فهمم دقیقاً روی چه چیزی کار کنم.»', 'visible' => true],
                    ['initials' => 'د.م', 'name' => 'دنیا مرادی', 'result' => 'دانش‌آموز دهم انسانی',
                     'from' => 'accent_rose', 'to' => 'accent_violet',
                     'text' => '«مشاورم عادت مطالعه‌ام را از صفر ساخت؛ حالا بدون استرس و منظم پیش می‌روم.»', 'visible' => true],
                ],
            ],

            'faq' => [
                'heading' => 'سؤال‌های پرتکرار',
                'subheading' => 'اگر جوابت اینجا نیست، در گفت‌وگوی آشنایی بپرس.',
                'items' => [
                    ['question' => 'مشاورم چطور انتخاب می‌شود؟',
                     'answer' => 'در گفت‌وگوی شناخت، پایه، رشته و نیازت را بررسی می‌کنیم و از میان سه مشاور تیم، نزدیک‌ترین همراه را به تو معرفی می‌کنیم.',
                     'visible' => true],
                    ['question' => 'چرا بعضی دانش‌آموزان دو مشاور دارند؟',
                     'answer' => 'در ماه‌های جمع‌بندی نهایی، یک مشاور دوم به تیم اضافه می‌شود تا پوشش و پیگیری فشرده‌تر شود؛ بقیه مسیر با همان یک مشاور اختصاصی پیش می‌رود.',
                     'visible' => true],
                    ['question' => 'کلاس گروهی هم برگزار می‌کنید؟',
                     'answer' => 'نه. سلام کلاس و گروه‌بندی ندارد؛ همه‌چیز فردی است: برنامه اختصاصی، گفت‌وگوی یک‌به‌یک و تحلیل آزمون خودت.',
                     'visible' => true],
                    ['question' => 'گفت‌وگوها و برنامه‌ها کجا ثبت می‌شود؟',
                     'answer' => 'همه‌چیز در پنل سلام است: برنامه هفتگی، نتایج آزمون‌ها، کارنامه و گفت‌وگوی مستقیم با مشاورت — هر وقت، همه‌جا در دسترس.',
                     'visible' => true],
                ],
            ],

            // The team strip: real consultants, real focus areas. Pure config.
            'blocks' => [
                'items' => [
                    ['type' => 'heading', 'title' => 'تیم مشاوران سلام', 'text' => 'سه همراه متخصص؛ هر کدام با یک تخصص که در گفت‌وگوی هفتگی‌ات حسش می‌کنی.', 'align' => 'center', 'visible' => true],
                    ['type' => 'card', 'title' => 'بهاره نظری', 'text' => 'برنامه‌ریزی متعادل، عادت‌های مطالعه و کاهش اضطراب آزمون.', 'fa_icon' => 'fa-solid fa-list-check', 'accent' => 'accent_amber', 'visible' => true],
                    ['type' => 'card', 'title' => 'امیرحسین کریمی', 'text' => 'تحلیل آزمون‌های ریاضی و پیگیری پیشرفت هفتگی.', 'fa_icon' => 'fa-solid fa-chart-line', 'accent' => 'accent_orange', 'visible' => true],
                    ['type' => 'card', 'title' => 'سپیده رحیمی', 'text' => 'انتخاب مسیر تحصیلی و تقویت مهارت مرور و خلاصه‌نویسی.', 'fa_icon' => 'fa-solid fa-flag', 'accent' => 'accent_rose', 'visible' => true],
                    ['type' => 'button', 'title' => 'رزرو گفت‌وگوی آشنایی', 'href' => '#cta', 'style' => 'solid', 'icon' => 'sparkle', 'visible' => true],
                    ['type' => 'divider', 'visible' => true],
                ],
            ],

            'blog' => [
                'count' => 3,
                'heading' => 'راهنمای مطالعه و کنکور',
                'subheading' => 'یادداشت‌های مشاوران سلام: از مرور اصولی تا مدیریت ماه‌های آخر.',
                'see_all' => ['label' => 'مشاهده همه', 'href' => '#blog', 'visible' => true],
            ],

            'cta' => [
                'heading' => 'سال کنکور، تنها نیستی',
                'text' => 'یک گفت‌وگوی آشنایی، بدون تعهد؛ ببین مشاوره فردی سلام چطور برای تو کار می‌کند.',
                'buttons' => [
                    ['label' => 'رزرو گفت‌وگوی آشنایی', 'route' => 'login', 'style' => 'solid', 'icon' => 'sparkle', 'visible' => true],
                    ['label' => 'ورود به حساب کاربری', 'route' => 'login', 'style' => 'ghost',  'icon' => null,     'visible' => true],
                ],
            ],
        ],

        'footer' => [
            'variant'    => 'columns',
            'background' => 'surface',
            'blurb' => 'مشاوره فردی کنکور برای دهم تا دوازدهم؛ با مشاور اختصاصی، برنامه هفتگی و تحلیل آزمون. بدون کلاس، بدون گروه‌بندی.',
            'social' => [
                ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => '#', 'visible' => true],
                ['icon' => 'fa-brands fa-telegram',  'label' => 'Telegram',  'url' => '#', 'visible' => true],
            ],
            'columns' => [
                ['title' => 'آکادمی', 'links' => [
                    ['label' => 'درباره ما',     'href' => '#about'],
                    ['label' => 'مسیر مشاوره',  'href' => '#process'],
                    ['label' => 'چرا فردی؟',    'href' => '#comparison'],
                    ['label' => 'وبلاگ',         'href' => '#blog'],
                ]],
                ['title' => 'همراهان', 'links' => [
                    ['label' => 'سوالات متداول',  'href' => '#faq'],
                    ['label' => 'ورود دانش‌آموز', 'href' => '#cta'],
                    ['label' => 'تماس با ما',     'href' => '#'],
                ]],
            ],
        ],
    ],
];
