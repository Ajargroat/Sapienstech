<?php

/*
|--------------------------------------------------------------------------
| Tenant: drkafimentoring  (domain: drkafimentoring.ir)
|--------------------------------------------------------------------------
|
| The tenant config for drkafimentoring.ir — a single consultant
| (علیرضا کافی), non-hierarchical, structurally like tenant-one. The look is
| the dark-luminous archetype (config/archetypes/dark_luminous.php): a deep
| navy ground, aurora washes, glass cards and glowing accents. This file only
| names that archetype and carries the tenant's real Persian copy — it does
| NOT restate the design, so the tenant stays a selector rather than a second
| copy of the theme. Only keys that differ from config/theme.php belong here.
|
| The copy below is real Persian prose written for this consultant; only the
| name and "۴ سال تجربه مشاوره" are facts taken from the user.
|
*/

return [
    'tenant' => [
        'name' => 'علیرضا کافی',
        'short_name' => 'علیرضا کافی',
        'role_label' => 'پنل مشاور',
        'page_title' => 'داشبورد مشاور',
    ],

    'theme' => [
        // The look lives in config/archetypes/dark_luminous.php. This file
        // only names it: every value below is deliberately left to the
        // archetype so the tenant inherits the rich identity and stays a
        // selector, not a second copy of the design.
        'archetype' => 'dark_luminous',
    ],

    'public' => [
        'nav' => [
            'links_style' => 'plain',
            'cta_style' => 'solid',
            'links' => [
                ['label' => 'درباره', 'href' => '#about',    'visible' => true],
                ['label' => 'فرایند', 'href' => '#process',  'visible' => true],
                ['label' => 'خدمات',  'href' => '#services', 'visible' => true],
                // The way in for a visitor who has no account: the priced plans
                // are the first stop of the join flow. Without it the only nav
                // destination was the login page, which a new student cannot
                // use.
                ['label' => 'طرح‌ها', 'href' => '#plans',    'visible' => true],
            ],
            'cta' => ['label' => 'ورود', 'route' => 'login', 'visible' => true],
        ],

        'landing' => [
            'meta' => [
                'title' => 'مشاوره تحصیلی',
                'description' => 'مشاوره تحصیلی فردی با علیرضا کافی — انتخاب رشته، برنامه‌ریزی مطالعه و همراهی تا کنکور.',
            ],

            // The accepted demo order. Every surface that would need invented
            // numbers (stats, testimonials, blog, faq, logos) is omitted.
            //
            // `join` is the opt-in for the membership request form: the visitor
            // imports their own details and declares the payment, the record
            // waits in the consultant's «درخواست‌ها» queue, and approving it
            // creates the student. It sits after `services` (where a visitor
            // decides) and before `cta` (the closing band). Copy comes from the
            // baseline `public.landing.join`; this tenant sets no override.
            //
            // `plans` sits immediately before it: it is where a price is
            // *determined*, so the join form can ask which plan the visitor
            // paid for instead of showing no price at all. The two are a pair —
            // plans without join has nothing to buy, join without plans cannot
            // state a price.
            'sections' => ['hero', 'advisor', 'process', 'services', 'plans', 'join', 'cta'],

            'hero' => [
                'eyebrow' => 'مشاوره تحصیلی فردی',
                'title_line1' => 'مسیر روشن،',
                'title_line2' => 'تصمیم آرام.',
                'subtitle' => 'همراهی فردی و پیوسته برای انتخاب رشته، ساختن برنامهٔ مطالعه و رسیدن آرام به هدف؛ از یک مشاور، نه یک مؤسسه.',
                'buttons' => [
                    // Was '#', which went nowhere. The plans grid is the first
                    // step of the join flow, so the primary action now lands
                    // there; the visitor picks a plan, then declares payment.
                    ['label' => 'رزرو وقت مشاوره', 'href' => '#plans', 'style' => 'primary', 'visible' => true],
                    ['label' => 'درباره من',       'href' => '#about', 'style' => 'ghost',   'visible' => true],
                ],
            ],

            'advisor' => [
                'id' => 'about',
                'image' => 'images/advisor.svg',
                'image_alt' => 'علیرضا کافی',
                'image_side' => 'start',
                'spin_rings' => false,
                'grayscale' => false,
                'badge' => ['label' => 'مشاوره فعال', 'visible' => false],
                'eyebrow' => ['icon' => 'fa-solid fa-certificate', 'label' => 'درباره مشاور'],
                'name' => 'علیرضا کافی',
                'tagline' => 'مشاور تحصیلی',
                'tagline_color' => 'secondary',
                'bio' => 'دانشجوی سال چهارم پزشکی دانشگاه علوم پزشکی تهران
                دانشجوی رشته‌ی اقتصاد دانشگاه تهران
                کسب مدال نقره کشوری المپیاد شیمی
                +۳ سال سابقه مشاوره و تدریس در زمینه کنکور و المپیاد
                مسئول سابق دپارتمان المپیادهای علمی استان یزد',
                // The one real fact the demo states. The baseline's three
                // invented rows (config/theme.php:777-781) are not carried.
                'stats' => [
                    ['value' => '۳', 'label' => 'سال تجربه مشاوره'],
                ],
                // The demo's advisor has no call to action.
                'buttons' => [],
            ],

            'process' => [
                'id' => 'process',
                'heading' => 'مسیر کار، گام به گام',
                // The baseline ships this list empty (config/theme.php:920), so
                // the section renders nothing without it.
                'items' => [
                    ['title' => 'جلسه شناخت',  'text' => 'در یک گفت‌وگوی صمیمی، وضعیت درسی، علاقه‌ها و هدف‌های دانش‌آموز را می‌شنویم و تصویر اولیه را می‌سازیم.', 'visible' => true],
                    ['title' => 'تحلیل وضعیت', 'text' => 'نمره‌ها، نتایج آزمون‌های آزمایشی و عادت‌های مطالعه را با هم بررسی می‌کنیم تا نقطهٔ شروع روشن باشد.', 'visible' => true],
                    ['title' => 'طراحی مسیر',  'text' => 'بر پایهٔ همان تصویر، یک برنامهٔ هفتگی و فهرست گام‌های بعدی نوشته می‌شود؛ روشن و قابل اجرا.', 'visible' => true],
                    ['title' => 'پیگیری',      'text' => 'هر هفته برنامه بازبینی می‌شود و در جلسه‌های منظم، پیشرفت سنجیده و مسیر اصلاح می‌شود.', 'visible' => true],
                ],
            ],

            'services' => [
                'id' => 'services',
                'heading' => 'چه چیزی ارائه می‌شود',
                // Cleared so the baseline's copy does not leak through.
                'subheading' => null,
                'columns' => 3,
                'items' => [
                    ['title' => 'انتخاب رشته',        'text' => 'شناخت رشته‌ها و بازار کارشان، بررسی کارنامه و رتبه، و انتخاب نهایی بر پایهٔ علاقه و توان واقعی.', 'icon' => null, 'accent' => 'primary', 'visible' => true],
                    ['title' => 'برنامه‌ریزی مطالعه', 'text' => 'برنامهٔ هفتگی متناسب با پایه و رشته، همراه با روش مطالعهٔ هر درس و مدیریت زمان.', 'icon' => null, 'accent' => 'primary', 'visible' => true],
                    ['title' => 'همراهی آزمون',       'text' => 'تحلیل کارنامهٔ آزمون‌های آزمایشی، شناسایی درس‌های ضعیف و تعیین تمرین‌های جبرانی هفتهٔ بعد.', 'icon' => null, 'accent' => 'primary', 'visible' => true],
                ],
            ],

            /*
            | Paying plans («طرح‌ها»). This is where a price is determined; the
            | join form below only lets the visitor name one of these slugs, and
            | the amount on the request is read from here server-side.
            |
            | The three-month plan at ۳ میلیون تومان is the confirmed figure —
            | it is the one the consultant actually charges, and the other two
            | are its single-month and full-year equivalents priced in the same
            | proportion. Only the first two are shown by default; the annual one
            | is visible because a full year is the cheapest per month.
            |
            | `features` is newline-separated: the studio's list control cannot
            | nest a list inside a row, so a nested shape would be uneditable.
            */
            'plans' => [
                'id' => 'plans',
                'heading' => 'طرح‌های مشاوره',
                'subheading' => 'طرح مناسب خود را انتخاب کنید؛ پس از پرداخت، فرم عضویت را پر کنید تا حساب شما ساخته شود.',
                'cta_label' => 'انتخاب این طرح',
                'cta_href' => '#join',
                'columns' => 3,
                'items' => [
                    [
                        'slug' => 'monthly',
                        'name' => 'ماهانه',
                        'price' => 1200000,
                        'period' => 'یک ماه',
                        'features' => "جلسهٔ مشاورهٔ اختصاصی در ماه\nبرنامهٔ مطالعهٔ هفتگی\nپاسخ به پرسش‌های درسی در طول ماه",
                        'cta_label' => null,
                        'badge' => null,
                        'featured' => false,
                        'visible' => true,
                    ],
                    [
                        'slug' => 'quarterly',
                        'name' => 'سه‌ماهه',
                        'price' => 3000000,
                        'period' => 'سه ماه',
                        'features' => "چهار جلسهٔ مشاورهٔ اختصاصی در ماه\nبرنامهٔ مطالعهٔ هفتگی و بازبینی آن\nتحلیل کارنامهٔ آزمون‌های آزمایشی\nپاسخ به پرسش‌های درسی در طول دوره",
                        'cta_label' => null,
                        'badge' => 'پیشنهاد ما',
                        'featured' => true,
                        'visible' => true,
                    ],
                    [
                        'slug' => 'annual',
                        'name' => 'سالانه',
                        'price' => 10800000,
                        'period' => 'یک سال',
                        'features' => "همهٔ امکانات طرح سه‌ماهه\nهمراهی کامل تا کنکور\nاولویت در زمان‌بندی جلسه‌ها\nبازبینی برنامه در فصل‌های حساس",
                        'cta_label' => null,
                        'badge' => null,
                        'featured' => false,
                        'visible' => true,
                    ],
                ],
            ],

            'cta' => [
                'id' => 'cta',
                'heading' => 'برای شروع، یک گفت‌وگو کافی است.',
                'text' => 'اگر نمی‌دانید از کجا شروع کنید، یک جلسهٔ کوتاه کافی است تا مسیر و گام‌های بعدی روشن شود.',
                'buttons' => [
                    // The closing band is the last thing a visitor sees, so it
                    // points at the plans grid (the start of the join flow)
                    // rather than '#'. The CTA schema addresses this as a
                    // destination rather than a URL, hence the `route` key with
                    // an anchor — _button.blade.php uses a '#'-prefixed value
                    // verbatim instead of passing it to route().
                    ['label' => 'رزرو وقت مشاوره', 'route' => '#plans', 'style' => 'primary', 'visible' => true],
                ],
            ],
        ],

        // The demo's footer is brand + copyright only: no blurb, no social,
        // no link columns. `variant => minimal` is what skips the first two
        // (resources/views/Public/sections/footer.blade.php:28, 41).
        'footer' => [
            'variant' => 'minimal',
            'background' => 'background',
            'show_logo_mark' => false,
            'social' => [],
            'columns' => [],
        ],
    ],
];
