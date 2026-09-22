<?php

/*
|--------------------------------------------------------------------------
| Tenant: moein  (domain: moeinacademy.test)
|--------------------------------------------------------------------------
|
| مؤسسه معین — a full middle school (متوسطه اول، grades 7-9), not a
| consultancy. Hierarchy: principal مریم فرهمند, 9 subject teachers, 9
| classrooms (هفتم/هشتم/نهم × الف/ب/ج), ~45 students. Teacher panel ON.
|
| Audience: parents of 12-15 year olds first, students second, teachers
| third. So the landing speaks like a school, not a platform: enrollment
| path, classrooms, teachers by name, report cards, parent voice.
|
| Visual identity comes from the editorial_serif archetype (warm paper,
| Amiri display headings, hairline rules, numbered services, quote-first
| advisor). Only keys that differ from theme.php / the archetype belong
| here; lists (sections, items, buttons) replace wholesale on merge.
|
*/

return [

    'tenant' => [
        'name'       => 'مؤسسه معین',
        'short_name' => 'مؤسسه معین',
        'role_label' => 'پنل مدرسه',
        'page_title' => 'داشبورد مدرسه',
    ],

    'labels' => [
        'dashboard_heading' => 'داشبورد مدرسه',
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
        'teacher_panel' => true,
        'teacher_materials' => true,
        'teacher_assignments' => true,
        'teacher_schedule' => true,
        'student_materials' => true,
        'student_assignments' => true,
        'student_timetable' => true,
        'appearance_staff_publish' => false,
    ],

    'theme' => [
        'archetype' => 'editorial_serif',
        'icons' => ['set' => 'tabler'], // Independent of the other tenants.
        'typography' => [
            'font_family' => 'Vazirmatn, sans-serif',
            'font_heading' => 'Amiri, serif',
            'font_accent' => 'Amiri, serif',
            'font_button' => 'Vazirmatn, sans-serif',
            // Lists replace the baseline: retain Vazirmatn alongside Amiri.
            // NOTE: Amiri faces are not bundled yet (public/fonts holds only
            // Vazirmatn); headings fall back to serif until the two Amiri
            // files below are deployed manually.
            'faces' => [
                ['family' => 'Vazirmatn', 'src' => '/fonts/vazirmatn/Vazirmatn[wght].woff2', 'weight' => '100 900'],
                ['family' => 'Amiri', 'src' => '/fonts/amiri/Amiri-Regular.woff2', 'weight' => '400'],
                ['family' => 'Amiri', 'src' => '/fonts/amiri/Amiri-Bold.woff2', 'weight' => '700'],
            ],
            'stat_size' => 'clamp(2rem, 4vw, 3rem)',
        ],

        // The base palette is warm paper, so the page boots LIGHT and the
        // toggle offers the dark reading of the same paper stock. Declaring
        // `default_scheme` is what makes the toggle real: before this, the
        // control started on a hardcoded 'dark' that had no `schemes.dark`
        // declaration, so both destinations resolved to the paper palette and
        // tapping it appeared to do nothing.
        'default_scheme' => 'light',

        // Warm-paper light scheme to match the editorial ground. Same
        // ink-on-paper contrast as the archetype itself.
        'schemes' => [
            'light' => [
                'background'       => '#FBF9F5',
                'surface'          => '#FFFFFF',
                'surface_alt'      => '#F3EFE7',
                'surface_elevated' => '#FFFFFF',
                'text'             => '#14161A',
            ],
            // The dark half: the same warm ink stock, inverted. Only the six
            // primitives are named; borders, glass, muted text and shadows all
            // recompute from them (ThemeTokens::colors), so nothing here can
            // drift from the light palette.
            'dark' => [
                'background'       => '#191714',
                'surface'          => '#211E1A',
                'surface_alt'      => '#2A2621',
                'surface_elevated' => '#2A2621',
                'text'             => '#F5F0E6',
                // The brand ink is too dark to read on a dark ground, so the
                // accent roles lighten while keeping their hue.
                'primary'          => '#9DB8DC',
                'secondary'        => '#E8A45C',
            ],
        ],
    ],

    'academics' => [
        'default_group' => 'intermediate',
        'subjects' => ['ریاضی', 'علوم تجربی', 'فیزیک', 'زیست‌شناسی', 'شیمی', 'فارسی', 'عربی', 'زبان انگلیسی', 'مطالعات اجتماعی'],
    ],

    'public' => [
        'nav' => [
            // Hairline underline on hover — the print-rules language of the
            // editorial bundle.
            'links_style' => 'underline',
            'links' => [
                ['label' => 'درباره ما',      'href' => '#about',   'visible' => true],
                ['label' => 'مسیر ثبت‌نام',   'href' => '#process', 'visible' => true],
                ['label' => 'دبیران',         'href' => '#faculty', 'visible' => true],
                ['label' => 'کلاس‌ها',        'href' => '#classrooms', 'visible' => true],
                ['label' => 'وبلاگ',          'href' => '#blog',    'visible' => true],
            ],
            'cta' => [
                'label'   => 'ورود | ثبت‌نام',
                'route'   => 'login',
                'visible' => true,
            ],
        ],

        'landing' => [
            'meta' => [
                'title'       => 'مدرسه متوسطه اول · پایه‌های هفتم تا نهم',
                'description' => 'مؤسسه معین؛ مدرسه متوسطه اول با کلاس‌های الف، ب و ج، دبیران متخصص هر درس و همراهی نزدیک با خانواده‌ها.',
                'og_image'    => null,
            ],

            // Editorial lineup: trust bar + process replace the ecosystem
            // orbit; teachers get a free-composition strip of their own.
            // Faculty and classrooms read the tenant's live hierarchy via
            // TenantRoster: teachers for a school, consultants for a
            // consultancy. Sections with no data drop off the page.
            'sections' => ['hero', 'stats', 'logos', 'advisor', 'process', 'services', 'faculty', 'classrooms', 'testimonials', 'faq', 'blog', 'cta'],

            'hero' => [
                'title_line1' => 'یادگیری و رشد در کنار هم',
                'title_line2' => 'مؤسسه معین',
                'subtitle' => 'مدرسه متوسطه اول؛ پایه‌های هفتم تا نهم، کلاس‌های الف، ب و ج و همراهی دبیران متخصص در هر درس.',
                'eyebrow' => 'مدرسه متوسطه اول · ثبت‌نام سال تحصیلی جدید',
                'media' => 'none', // typographic hero; no photo asset shipped
                'buttons' => [
                    ['label' => 'آشنایی با مسیر ثبت‌نام', 'href' => '#process',  'style' => 'primary', 'icon' => 'arrow-down', 'visible' => true],
                    ['label' => 'آشنایی با دبیران',       'href' => '#faculty',  'style' => 'ghost',   'icon' => 'arrow',      'visible' => true],
                ],
            ],

            'stats' => [
                'variant' => 'inline-divider',
                // Roster-driven: values resolve from the tenant's own users,
                // classrooms and grades at request time. No deploy needed when
                // a teacher joins or a class opens — the page just updates.
                'source' => 'roster',
                'items' => [
                    ['key' => 'students',   'label' => 'دانش‌آموز',     'suffix' => '', 'gradient' => false, 'visible' => true],
                    ['key' => 'classrooms', 'label' => 'کلاس فعال',    'suffix' => '', 'gradient' => false, 'visible' => true],
                    ['key' => 'staff',      'label' => 'دبیر متخصص',   'suffix' => '', 'gradient' => false, 'visible' => true],
                    ['key' => 'grades',     'label' => 'پایه تحصیلی',  'suffix' => '', 'gradient' => false, 'visible' => true],
                ],
            ],

            'logos' => [
                'heading' => 'سه پایه، نه کلاس، یک همراهی نزدیک',
                'items' => [
                    ['name' => 'پایه هفتم', 'visible' => true],
                    ['name' => 'پایه هشتم', 'visible' => true],
                    ['name' => 'پایه نهم',  'visible' => true],
                ],
            ],

            'advisor' => [
                // quote-first renders no portrait when `image` is null, so no
                // broken image until a real portrait is uploaded.
                'image' => null,
                'image_alt' => 'مریم فرهمند',
                'eyebrow' => ['icon' => 'fa-solid fa-building', 'label' => 'مدیریت مدرسه'],
                'name' => 'مریم فرهمند',
                'tagline' => 'مدیر مدرسه و همراه خانواده‌ها',
                'badge' => ['label' => 'ثبت‌نام سال تحصیلی جدید', 'visible' => true],
                'bio' => 'در مدرسه معین، مدیر و دبیران با همکاری یکدیگر رشد تحصیلی و فردی هر دانش‌آموز را دنبال می‌کنند؛ از کلاس‌بندی دقیق تا گزارش مستمر به خانواده‌ها.',
                'stats' => [
                    ['value' => '۹',  'label' => 'کلاس در سه پایه'],
                    ['value' => '۹',  'label' => 'دبیر متخصص'],
                    ['value' => '۴۵', 'label' => 'دانش‌آموز'],
                ],
                'buttons' => [
                    ['label' => 'مشاهده مسیر ثبت‌نام', 'href' => '#process', 'style' => 'primary', 'visible' => true],
                    ['label' => 'ورود به پنل',         'href' => '#cta',     'style' => 'ghost',   'visible' => true],
                ],
            ],

            'process' => [
                'heading' => 'مسیر همراهی با معین',
                'subheading' => 'از اولین گفت‌وگو تا گزارش مستمر در طول سال تحصیلی.',
                'items' => [
                    ['title' => 'گفت‌وگوی آشنایی', 'text' => 'با مدرسه، دبیران و شیوه همراهی آشنا می‌شوید و سؤالاتتان را می‌پرسید.', 'visible' => true],
                    ['title' => 'ثبت‌نام و تشکیل پرونده', 'text' => 'پرونده تحصیلی دانش‌آموز تشکیل می‌شود و پایه و سوابق درسی ثبت می‌گردد.', 'visible' => true],
                    ['title' => 'کلاس‌بندی و آشنایی با دبیران', 'text' => 'دانش‌آموز در یکی از کلاس‌های الف، ب یا ج قرار می‌گیرد و با دبیران هر درس آشنا می‌شود.', 'visible' => true],
                    ['title' => 'سال تحصیلی با گزارش مستمر', 'text' => 'تکالیف، جزوه‌ها، کارنامه و گفت‌وگوی مستقیم با دبیران؛ خانواده‌ها همیشه در جریان‌اند.', 'visible' => true],
                ],
            ],

            'services' => [
                // Numbered list (from the archetype): one clear promise per row.
                'variant' => 'numbered-list',
                'heading' => 'در معین چه می‌گذرد',
                'subheading' => 'همان ماژول‌هایی که هر روز در پنل مدرسه استفاده می‌شوند.',
                'items' => [
                    ['accent' => 'primary',      'title' => 'جزوه‌ها و منابع هر درس',       'text' => 'منابع درسی هر پایه، مرتب و همیشه در دسترس دانش‌آموز در پنل.', 'visible' => true],
                    ['accent' => 'secondary',    'title' => 'تکالیف و پیگیری دبیر',         'text' => 'تکلیف هر درس از سوی دبیر ثبت و بازخورد توصیفی هفتگی داده می‌شود.', 'visible' => true],
                    ['accent' => 'accent_teal',  'title' => 'برنامه کلاسی و هفتگی',         'text' => 'برنامه هر کلاس (الف، ب، ج) و برنامه مطالعاتی هر دانش‌آموز، شفاف و به‌روز.', 'visible' => true],
                    ['accent' => 'accent_amber', 'title' => 'کارنامه و گزارش پیشرفت',       'text' => 'نمرات، آزمون‌ها و روند پیشرفت هر دانش‌آموز در قالب کارنامه دوره‌ای.', 'visible' => true],
                    ['accent' => 'accent_blue',  'title' => 'گفت‌وگوی مستقیم با دبیران',    'text' => 'خانواده و دانش‌آموز بدون واسطه با دبیر هر درس در ارتباط‌اند.', 'visible' => true],
                    ['accent' => 'accent_rose',  'title' => 'همراهی مدیر مدرسه',            'text' => 'نگاه یکپارچه مدیر بر رشد فردی و تحصیلی همه دانش‌آموزان سه پایه.', 'visible' => true],
                ],
            ],

            'testimonials' => [
                'variant' => 'single-featured',
                'heading' => 'صدای خانواده‌ها',
                'subheading' => 'آنچه والدین و دانش‌آموزان معین تجربه کرده‌اند. (نقل‌قول‌ها نمایشی‌اند.)',
                'items' => [
                    ['initials' => 'م.ک', 'name' => 'خانواده کریمی', 'result' => 'پدر دانش‌آموز پایه هشتم',
                     'from' => 'primary', 'to' => 'secondary',
                     'text' => '«از وقتی پسرم به معین آمد، دقیقاً می‌دانیم هر هفته در هر درس چه می‌گذرد. گفت‌وگو با دبیرها راحت است و کارنامه‌ها شفاف.»', 'visible' => true],
                    ['initials' => 'س.ا', 'name' => 'سارا احمدی', 'result' => 'دانش‌آموز پایه نهم',
                     'from' => 'accent_teal', 'to' => 'accent_blue',
                     'text' => '«جزوه‌ها و برنامه هفتگی همه‌چیز را مرتب کرده؛ می‌دانم هر روز دقیقاً چه بخوانم.»', 'visible' => true],
                    ['initials' => 'ر.م', 'name' => 'خانواده موسوی', 'result' => 'مادر دانش‌آموز پایه هفتم',
                     'from' => 'accent_amber', 'to' => 'accent_rose',
                     'text' => '«ورود به متوسطه اول برای دخترم استرس داشت، ولی همراهی دبیران در ماه اول فوق‌العاده بود.»', 'visible' => true],
                ],
            ],

            'faq' => [
                'heading' => 'پرسش‌های پرتکرار خانواده‌ها',
                'subheading' => 'کوتاه و شفاف؛ برای جزئیات بیشتر با مدرسه در تماس باشید.',
                'items' => [
                    ['question' => 'ثبت‌نام در معین چگونه انجام می‌شود؟',
                     'answer' => 'با یک گفت‌وگوی آشنایی شروع می‌کنیم، سپس پرونده تحصیلی دانش‌آموز تشکیل و پایه و سوابق درسی ثبت می‌شود. ظرفیت هر کلاس محدود است، پس بهتر است زودتر اقدام کنید.',
                     'visible' => true],
                    ['question' => 'کلاس‌های الف، ب و ج چه تفاوتی دارند؟',
                     'answer' => 'هر سه کلاس در هر پایه (هفتم، هشتم، نهم) با همان سرفصل‌ها و دبیران پیش می‌روند؛ تقسیم‌بندی برای کلاس‌های کم‌جمعیت و توجه بیشتر به هر دانش‌آموز است، نه سطح‌بندی.',
                     'visible' => true],
                    ['question' => 'ارتباط خانواده با دبیران چگونه است؟',
                     'answer' => 'از طریق گفت‌وگوی مستقیم پنل، بدون واسطه با دبیر هر درس در ارتباطید؛ دبیران علاوه بر تکالیف، بازخورد توصیفی هفتگی هم ثبت می‌کنند.',
                     'visible' => true],
                    ['question' => 'از پیشرفت تحصیلی فرزندم چطور باخبر می‌شوم؟',
                     'answer' => 'کارنامه‌های دوره‌ای، نتایج آزمون‌ها و روند پیشرفت هر درس در پنل دانش‌آموز ثبت می‌شود و مدیر مدرسه نیز بر رشد فردی هر دانش‌آموز نظارت دارد.',
                     'visible' => true],
                ],
            ],

            // The teaching staff, by name — resolved live from the dashboard
            // roster (TenantRoster::staff), not hand-written cards. Subjects
            // come from each teacher's real classroom assignments.
            'faculty' => [
                'columns' => 3,
                'limit' => 9,
                'show_bio' => true,
                'heading' => 'دبیران مؤسسه معین',
                'subheading' => 'متخصص هر درس، همراه هر کلاس؛ همان دبیرانی که هر روز در پنل می‌بینید.',
            ],

            // Classrooms by grade, read from the tenant's own hierarchy with
            // live student counts per room (TenantRoster::classrooms).
            'classrooms' => [
                'columns' => 3,
                'heading' => 'کلاس‌های مدرسه',
                'subheading' => 'سه پایه، سه کلاس در هر پایه؛ کلاس‌های کم‌جمعیت برای توجه بیشتر به هر دانش‌آموز.',
            ],

            'blog' => [
                'count' => 3,
                'heading' => 'از دفتر مدرسه',
                'subheading' => 'یادداشت‌های دبیران و مدیر مدرسه برای خانواده‌ها.',
                'see_all' => ['label' => 'مشاهده همه', 'href' => '#blog', 'visible' => true],
            ],

            'cta' => [
                'variant' => 'boxed-card',
                'heading' => 'برای سال تحصیلی جدید، زودتر آشنا شویم',
                'text' => 'ظرفیت کلاس‌های الف، ب و ج محدود است. یک گفت‌وگوی آشنایی، بهترین شروع است.',
                'buttons' => [
                    ['label' => 'درخواست گفت‌وگوی آشنایی', 'route' => 'login', 'style' => 'solid', 'visible' => true],
                    ['label' => 'ورود به حساب کاربری',   'route' => 'login', 'style' => 'ghost', 'visible' => true],
                ],
            ],
        ],

        'footer' => [
            // Centered colophon: brand and link columns on the centre axis,
            // sitting on the paper ground instead of a white surface card.
            'variant'    => 'centered',
            'background' => 'background',
            'blurb' => 'مدرسه متوسطه اول برای پایه‌های هفتم تا نهم؛ کلاس‌های کم‌جمعیت، دبیران متخصص و همراهی نزدیک با خانواده‌ها.',
            'social' => [
                ['icon' => 'fa-brands fa-instagram', 'label' => 'Instagram', 'url' => '#', 'visible' => true],
                ['icon' => 'fa-brands fa-telegram',  'label' => 'Telegram',  'url' => '#', 'visible' => true],
            ],
            'columns' => [
                ['title' => 'مدرسه', 'links' => [
                    ['label' => 'درباره ما',      'href' => '#about'],
                    ['label' => 'مسیر ثبت‌نام',  'href' => '#process'],
                    ['label' => 'دبیران',        'href' => '#faculty'],
                    ['label' => 'وبلاگ مدرسه',   'href' => '#blog'],
                ]],
                ['title' => 'همراهان', 'links' => [
                    ['label' => 'سوالات متداول', 'href' => '#faq'],
                    ['label' => 'ورود دانش‌آموز', 'href' => '#cta'],
                    ['label' => 'تماس با مدرسه',  'href' => '#'],
                ]],
            ],
        ],
    ],

];
