<?php

/*
|--------------------------------------------------------------------------
| Tenant: moein
|--------------------------------------------------------------------------
|
| Only the keys that differ from config/theme.php belong here; anything
| unset inherits the baseline. Add the tenant's own advisor, numbers,
| articles and footer copy under `public` as it becomes available.
|
*/

return [

    'tenant' => [
        'name'       => 'مؤسسه معین',
        'short_name' => 'مؤسسه معین',
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
            // Deploy these files manually; none are bundled with the app.
            'faces' => [
                ['family' => 'Vazirmatn', 'src' => '/fonts/vazirmatn/Vazirmatn[wght].woff2', 'weight' => '100 900'],
                ['family' => 'Amiri', 'src' => '/fonts/amiri/Amiri-Regular.woff2', 'weight' => '400'],
                ['family' => 'Amiri', 'src' => '/fonts/amiri/Amiri-Bold.woff2', 'weight' => '700'],
            ],
        ],
    ],

    'academics' => [
        'default_group' => 'intermediate',
        'subjects' => ['ریاضی', 'علوم تجربی', 'فیزیک', 'زیست‌شناسی', 'شیمی', 'فارسی', 'عربی', 'زبان انگلیسی', 'مطالعات اجتماعی'],
    ],

    'public' => [
        'landing' => [
            'hero' => [
                'title_line1' => 'یادگیری و رشد در کنار هم',
                'title_line2' => 'مؤسسه معین',
                'subtitle' => 'مدرسه نمایشی متوسطه اول؛ پایه‌های هفتم تا نهم، کلاس‌های الف، ب و ج و همراهی دبیران متخصص. اطلاعات این صفحه نمونه است.',
            ],
            'advisor' => [
                'name' => 'مریم فرهمند',
                'tagline' => 'مدیر مدرسه و همراه خانواده‌ها',
                'bio' => 'در مدرسه نمونه معین، مدیر و دبیران با همکاری یکدیگر رشد تحصیلی و فردی هر دانش‌آموز را دنبال می‌کنند. این معرفی برای نمایش سامانه تهیه شده است.',
            ],
            'stats' => ['items' => [
                ['value' => 45, 'suffix' => '', 'label' => 'دانش‌آموز نمونه', 'visible' => true],
                ['value' => 9, 'suffix' => '', 'label' => 'کلاس', 'visible' => true],
                ['value' => 9, 'suffix' => '', 'label' => 'دبیر نمونه', 'visible' => true],
            ]],
        ],
    ],

];

