<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant
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
    | consultant dashboard. Disabling a feature here will hide it from the
    | sidebar and block its routes via middleware.
    |
    | Multi-Tenant Security Note: These flags ensure that tenants only see
    | and can access the modules they are authorized to use.
    |
    */
    'features' => [

        /*
        |--------------------------------------------------------------------------
        | Core & Dashboard
        |--------------------------------------------------------------------------
        */
        'dashboard' => true,

        /*
        |--------------------------------------------------------------------------
        | Blog & Content Management
        |--------------------------------------------------------------------------
        */
        'blog_management'    => true,
        'create_post_action' => true, // Controls the "Create Post" button visibility

        /*
        |--------------------------------------------------------------------------
        | Student Management
        |--------------------------------------------------------------------------
        */
        'student_statistics' => true,
        'student_search'     => true,
        'student_filters'    => true,
        'student_sorting'    => true,
        'student_schedule'   => true,
        'student_quizzes'    => true,
        'report_cards'       => true,

        /*
        |--------------------------------------------------------------------------
        | Quizzes & Questions
        |--------------------------------------------------------------------------
        */
        'quiz_management'     => true,
        'question_management' => true,

        /*
        |--------------------------------------------------------------------------
        | Books & Resources
        |--------------------------------------------------------------------------
        */
        'book_access' => true,

    ],

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    */
    'sidebar' => [
        [
            'key'       => 'dashboard',
            'label_key' => 'dashboard',
            'route'     => 'consultant.dashboard',
            'icon'      => 'fa-chart-pie',
        ],
        [
            'key'       => 'blog_management',
            'label_key' => 'blog_management',
            'route'     => 'consultant.blog',
            'icon'      => 'fa-newspaper',
        ],
        [
            'key'       => 'book_access',
            'label_key' => 'book_access',
            'route'     => 'consultant.permissions',
            'icon'      => 'fa-book-reader',
        ],
        [
            'key'       => 'question_management',
            'label_key' => 'question_management',
            'route'     => 'consultant.questions',
            'icon'      => 'fa-file-circle-question',
        ],
        [
            'key'       => 'quiz_management',
            'label_key' => 'quiz_management',
            'route'     => 'consultant.quizzes',
            'icon'      => 'fa-list-check',
        ],
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
        ],

        'typography' => [
            'font_family'    => 'Vazirmatn',
            'body_size'      => '15px',
            'body_weight'    => '400',
            'heading_weight' => '800',
            'letter_spacing' => '0',
            'line_height'    => '1.8',
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
        // Sidebar
        'dashboard'            => 'داشبورد',
        'blog_management'      => 'مدیریت وبلاگ',
        'book_access'          => 'دسترسی کتاب',
        'question_management'  => 'مدیریت سوالات',
        'quiz_management'      => 'مدیریت آزمون‌ها',

        // Dashboard
        'dashboard_heading'    => 'داشبورد مشاور',
        'welcome_prefix'       => 'خوش آمدید،',
        'create_post'          => 'نوشته جدید',

        // Student stats
        'student_statistics'   => 'آمار دانش‌آموزان',
        'student_count'        => 'کل دانش‌آموزان',
        'active_quizzes'       => 'آزمون‌های فعال',

        // Filters
        'filters_heading'      => 'فیلتر و جستجوی پیشرفته',
        'search_placeholder'   => 'جستجوی نام...',
        'all_grades'           => 'همه پایه‌ها',
        'all_majors'           => 'همه رشته‌ها',
        'all_genders'          => 'جنسیت (همه)',
        'sort_asc'             => 'حروف الفبا (الف - ی)',
        'sort_desc'            => 'حروف الفبا (ی - الف)',

        // Student list
        'student_list'         => 'لیست دانش‌آموزان شما',
        'student_grade'        => 'پایه',
        'student_major'        => 'رشته',
        'schedule'             => 'برنامه',
        'quizzes'              => 'آزمون‌ها',
        'report_card'          => 'کارنامه',

        // Empty states
        'empty_students_title' => 'دانش‌آموزی یافت نشد',
        'empty_students_text'  => 'در حال حاضر هیچ دانش‌آموزی به شما اختصاص داده نشده است.',
        'empty_search_title'   => 'نتیجه‌ای یافت نشد',
        'empty_search_text'    => 'با فیلترهای اعمال شده دانش‌آموزی پیدا نشد.',

        // Misc
        'logout'               => 'خروج از حساب',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'grades' => [
            ['value' => '10', 'label' => 'پایه دهم'],
            ['value' => '11', 'label' => 'پایه یازدهم'],
            ['value' => '12', 'label' => 'پایه دوازدهم'],
        ],
        'majors' => [
            ['value' => 'تجربی', 'label' => 'تجربی'],
            ['value' => 'ریاضی', 'label' => 'ریاضی'],
            ['value' => 'انسانی', 'label' => 'انسانی'],
        ],
        'genders' => [
            ['value' => 'پسر', 'label' => 'پسر'],
            ['value' => 'دختر', 'label' => 'دختر'],
        ],
    ],

];
