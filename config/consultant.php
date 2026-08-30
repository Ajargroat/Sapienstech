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
            'text_on_primary'      => '#000000',
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
            'sidebar_radius'      => '24px',
            'student_avatar_size' => '40px',
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

        // Profile
        'profile_heading'      => 'پروفایل دانش‌آموز',
        'back_to_students'     => 'بازگشت به لیست دانش‌آموزان',
        'profile_form_heading' => 'ویرایش اطلاعات',
        'upload_photo'         => 'انتخاب تصویر',
        'save_photo'           => 'ذخیره تصویر',
        'save_changes'         => 'ذخیره تغییرات',

        // Field labels
        'student_name'  => 'نام',
        'student_email' => 'ایمیل',
        'student_grade' => 'پایه',
        'student_gender' => 'جنسیت',
        'student_major' => 'رشته',

        // Misc
        'logout' => 'خروج از حساب',
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Profile
    |--------------------------------------------------------------------------
    |
    | Controls how the student profile page is rendered and how avatar
    | uploads are handled.  Every label references a key from the labels
    | section above so that translations stay centralised.
    |
    */
    'profile' => [
        'avatar' => [
            'disk'        => 'public',
            'path'        => 'avatars',
            'fallback'    => 'initials',
            'max_size_kb' => 1024,
            'mimes'       => 'jpeg,jpg,png,webp',
        ],

        'form' => [
            'method' => 'PUT',
            'fields' => [
                [
                    'key'         => 'name',
                    'label'       => 'student_name',
                    'type'        => 'text',
                    'required'    => true,
                    'placeholder' => 'نام کامل دانش‌آموز',
                ],
                [
                    'key'         => 'email',
                    'label'       => 'student_email',
                    'type'        => 'email',
                    'required'    => true,
                    'placeholder' => 'example@domain.com',
                    'ltr'         => true,
                ],
                [
                    'key'         => 'grade',
                    'label'       => 'student_grade',
                    'type'        => 'text',
                    'required'    => false,
                    'placeholder' => 'مثلاً: دهم',
                ],
                [
                    'key'         => 'gender',
                    'label'       => 'student_gender',
                    'type'        => 'text',
                    'required'    => false,
                    'placeholder' => 'مثلاً: دختر',
                ],
                [
                    'key'         => 'major',
                    'label'       => 'student_major',
                    'type'        => 'text',
                    'required'    => false,
                    'placeholder' => 'مثلاً: ریاضی فیزیک',
                ],
            ],
        ],

        // Fields shown read-only on the profile page: model column => label key
        'display_fields' => [
            'grade'  => 'student_grade',
            'gender' => 'student_gender',
            'major'  => 'student_major',
        ],
    ],

];
