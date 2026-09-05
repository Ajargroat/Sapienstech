<?php

/*
|--------------------------------------------------------------------------
| Tenant: tenant-one  (domain: tenant1.sapienstech.local)
|--------------------------------------------------------------------------
|
| Everything that identifies THIS academy rather than the platform: its name,
| its advisor, its numbers, its articles, its footer.
|
| Lifted verbatim from the old config/consultant.php, so the copy is byte
| identical to what shipped before the split.
|
| Only keys that differ from config/theme.php belong here. Anything unset
| inherits the baseline, which is why adding a tenant is a file this short
| rather than a 470-line copy.
|
*/

return [
    'tenant' => [
        'name' => 'آکادمی جنت',
        'short_name' => 'آکادمی جنت',
    ],

    'theme' => [
        'archetype' => 'aurora_glass',
    ],

    'public' => [
        'landing' => [
            'hero' => [
                'title_line1' => 'آینده یادگیری با',
                'title_line2' => 'آکادمی جنت',
                'subtitle' => 'پلتفرم یکپارچه آموزش هوشمند. مسیر تحصیلی خود را با برنامه‌ریزی اختصاصی، تحلیل‌های دقیق و سیستم مدیریت یادگیری مدرن متحول کنید.',
            ],
            'advisor' => [
                'id' => 'about',
                'image' => 'images/advisor.jpg',
                'image_alt' => 'محمدرضا جنت‌فریدونی',
                'image_side' => 'start',
                'image_size' => '18rem',
                'spin_rings' => true,
                'grayscale' => true,
                'badge' => [
                    'label' => 'مشاوره فعال',
                    'visible' => true,
                ],
                'eyebrow' => [
                    'icon' => 'fa-solid fa-certificate',
                    'label' => 'مدیریت و مشاوره ارشد',
                ],
                'name' => 'محمدرضا جنت‌فریدونی',
                'tagline' => 'طراح مسیر موفقیت تحصیلی شما',
                'tagline_color' => 'secondary',
                'bio' => 'ترکیب سال‌ها تجربه در زمینه مشاوره تحصیلی با قدرت تحلیل هوش مصنوعی، به ما این امکان را می‌دهد که برای هر دانش‌آموز یک نقشه راه اختصاصی طراحی کنیم. من اینجا هستم تا در کنار پلتفرم هوشمند آکادمی جنت، مسیر شما را تا رسیدن به قله‌های موفقیت هموار کنم و در تمام چالش‌های تحصیلی راهنمای شما باشم.',
                'stats' => [
                    [
                        'value' => '۴+',
                        'label' => 'سال تجربه',
                    ],
                    [
                        'value' => '۱۰۰+',
                        'label' => 'دانش‌آموز موفق',
                    ],
                    [
                        'value' => '۱۰۰٪',
                        'label' => 'پشتیبانی اختصاصی',
                    ],
                ],
                'buttons' => [
                    [
                        'label' => 'رزرو وقت مشاوره',
                        'href' => '#',
                        'style' => 'primary',
                        'visible' => true,
                    ],
                    [
                        'label' => 'مشاهده رزومه کامل',
                        'href' => '#',
                        'style' => 'ghost',
                        'visible' => true,
                    ],
                ],
            ],
            'ecosystem' => [
                'text' => 'ما با ترکیب الگوریتم‌های پیشرفته هوش مصنوعی و متدهای نوین یادگیری، محیطی را فراهم کرده‌ایم که در آن هر دانش‌آموز بر اساس نقاط قوت و ضعف خود، مسیر تحصیلی شخصی‌سازی شده‌ای را طی می‌کند.',
            ],
            'services' => [
                'items' => [
                    [
                        'icon' => 'fa-solid fa-bolt',
                        'accent' => 'primary',
                        'title' => 'مشاوره هوشمند',
                        'text' => 'تحلیل لحظه‌ای وضعیت تحصیلی و ارائه پیشنهادات هوشمندانه برای بهبود راندمان.',
                    ],
                    [
                        'icon' => 'fa-solid fa-calendar-days',
                        'accent' => 'secondary',
                        'title' => 'برنامه‌ریزی شخصی',
                        'text' => 'تولید برنامه مطالعاتی دینامیک بر اساس اهداف، زمان خالی و سرعت یادگیری شما.',
                    ],
                    [
                        'icon' => 'fa-solid fa-chart-line',
                        'accent' => 'accent-blue',
                        'title' => 'تحلیل عملکرد',
                        'text' => 'نمودارهای پیشرفته و داشبورد مدیریتی برای رصد دقیق پیشرفت تحصیلی.',
                    ],
                    [
                        'icon' => 'fa-solid fa-circle-check',
                        'accent' => 'accent-emerald',
                        'title' => 'آزمون‌های آزمایشی',
                        'text' => 'بانک سوالات استاندارد همراه با سیستم برگزاری آزمون مشابه شرایط واقعی.',
                    ],
                    [
                        'icon' => 'fa-solid fa-box-archive',
                        'accent' => 'accent-orange',
                        'title' => 'مدیریت یادگیری',
                        'text' => 'دسترسی به محتوای آموزشی، ویدیوها و جزوات در یک پلتفرم متمرکز.',
                    ],
                    [
                        'icon' => 'fa-solid fa-robot',
                        'accent' => 'primary',
                        'title' => 'دستیار هوش مصنوعی',
                        'text' => 'پاسخگویی ۲۴ ساعته به سوالات درسی و رفع اشکالات توسط دستیار اختصاصی.',
                    ],
                ],
            ],
            'stats' => [
                'items' => [
                    [
                        'value' => 14,
                        'suffix' => '+',
                        'label' => 'دانش‌آموز فعال',
                        'gradient' => false,
                        'visible' => true,
                    ],
                    [
                        'value' => 91,
                        'suffix' => '٪',
                        'label' => 'رضایت و موفقیت',
                        'gradient' => true,
                        'visible' => true,
                    ],
                    [
                        'value' => 2000,
                        'suffix' => '+',
                        'label' => 'ساعت مشاوره',
                        'gradient' => false,
                        'visible' => true,
                    ],
                    [
                        'value' => 800,
                        'suffix' => '+',
                        'label' => 'آزمون برگزار شده',
                        'gradient' => false,
                        'visible' => true,
                    ],
                ],
            ],
            'testimonials' => [
                'items' => [
                    [
                        'initials' => 'ع.ر',
                        'name' => 'علی رضایی',
                        'result' => 'رتبه ۱۵۲ کنکور ریاضی',
                        'from' => 'accent-blue',
                        'to' => 'secondary',
                        'text' => '«دستیار هوش مصنوعی و تحلیل‌های دقیق نموداری به من کمک کرد تا نقاط ضعفم را در دروس اختصاصی پیدا کنم. برنامه‌ریزی‌ها کاملا منطبق بر توانایی من بود.»',
                        'visible' => true,
                    ],
                    [
                        'initials' => 'س.م',
                        'name' => 'سارا محمدی',
                        'result' => 'قبولی پزشکی تهران',
                        'from' => 'primary',
                        'to' => 'accent-orange',
                        'text' => '«بانک سوالات و آزمون‌های شبیه‌ساز کاملا مشابه کنکور بودند. استرس من در جلسه اصلی به خاطر تمرین در این پلتفرم بسیار کم بود.»',
                        'visible' => true,
                    ],
                    [
                        'initials' => 'ا.ک',
                        'name' => 'امیرحسین کریمی',
                        'result' => 'رتبه ۸۷ کنکور انسانی',
                        'from' => 'accent-emerald',
                        'to' => 'accent-teal',
                        'text' => '«لذت‌بخش‌ترین قسمت برای من رابط کاربری مینیمال و بدون حواس‌پرتی پلتفرم بود. همه چیز سر جای خودش قرار داشت و سرعت پیشرفتم دو برابر شد.»',
                        'visible' => true,
                    ],
                ],
            ],
            'blog' => [
                'items' => [
                    [
                        'image' => 'images/blog-pics/blog-pic1.png',
                        'from' => 'primary',
                        'to' => 'secondary',
                        'title' => 'چگونه هوش مصنوعی سبک یادگیری را تغییر می‌دهد؟',
                        'excerpt' => 'بررسی جامع تاثیر الگوریتم‌های هوش مصنوعی بر شخصی‌سازی آموزش و افزایش بازدهی دانش‌آموزان در قرن جدید.',
                        'url' => '#',
                        'visible' => true,
                    ],
                    [
                        'image' => 'images/blog-pics/blog-pic2.png',
                        'from' => 'accent-blue',
                        'to' => 'accent-teal',
                        'title' => 'مدیریت زمان در سال کنکور',
                        'excerpt' => 'تکنیک‌های اثبات‌شده برای مدیریت زمان، جلوگیری از فرسودگی ذهنی و تنظیم یک برنامه مطالعاتی متعادل.',
                        'url' => '#',
                        'visible' => true,
                    ],
                    [
                        'image' => 'images/blog-pics/blog-pic3.png',
                        'from' => 'accent-orange',
                        'to' => 'accent-red',
                        'title' => 'تحلیل کارنامه: قدم اول پیشرفت',
                        'excerpt' => 'چگونه داده‌های کارنامه آزمون آزمایشی را تفسیر کنیم و از آن‌ها برای تنظیم استراتژی ماه آینده استفاده کنیم.',
                        'url' => '#',
                        'visible' => true,
                    ],
                ],
            ],
            'cta' => [
                'text' => 'همین حالا به جمع هزاران دانش‌آموز موفق ما بپیوندید و یادگیری را به سطح جدیدی ارتقا دهید.',
            ],
        ],
        'footer' => [
            'blurb' => 'ما آینده آموزش را با ترکیب هوش مصنوعی، طراحی انسان‌محور و تکنولوژی‌های روز دنیا می‌سازیم. یادگیری هوشمند، حق همه است.',
            'social' => [
                [
                    'icon' => 'fa-brands fa-x-twitter',
                    'label' => 'X',
                    'url' => '#',
                    'visible' => true,
                ],
                [
                    'icon' => 'fa-brands fa-instagram',
                    'label' => 'Instagram',
                    'url' => '#',
                    'visible' => true,
                ],
                [
                    'icon' => 'fa-brands fa-telegram',
                    'label' => 'Telegram',
                    'url' => '#',
                    'visible' => true,
                ],
            ],
            'columns' => [
                [
                    'title' => 'دسترسی سریع',
                    'links' => [
                        [
                            'label' => 'درباره ما',
                            'href' => '#about',
                        ],
                        [
                            'label' => 'خدمات سیستم',
                            'href' => '#services',
                        ],
                        [
                            'label' => 'داستان موفقیت',
                            'href' => '#testimonials',
                        ],
                        [
                            'label' => 'وبلاگ آموزشی',
                            'href' => '#blog',
                        ],
                    ],
                ],
                [
                    'title' => 'پشتیبانی',
                    'links' => [
                        [
                            'label' => 'سوالات متداول',
                            'href' => '#',
                        ],
                        [
                            'label' => 'حریم خصوصی',
                            'href' => '#',
                        ],
                        [
                            'label' => 'شرایط استفاده',
                            'href' => '#',
                        ],
                        [
                            'label' => 'تماس با ما',
                            'href' => '#',
                        ],
                    ],
                ],
            ],
        ],
    ],

];
