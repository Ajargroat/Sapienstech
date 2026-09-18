<?php

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
        'archetype' => 'aurora_glass',
        'icons' => ['set' => 'lucide'],
    ],

    'academics' => [
        'default_group' => 'high_school',
        'subjects' => ['ریاضی', 'فیزیک', 'شیمی', 'زیست‌شناسی', 'ادبیات', 'عربی', 'علوم اجتماعی'],
    ],

    'public' => [
        'landing' => [
            'hero' => [
                'title_line1' => 'یک مسیر، چند همراه متخصص',
                'title_line2' => 'آکادمی سلام',
                'subtitle' => 'مشاوره و برنامه‌ریزی کنکور برای پایه‌های دهم تا دوازدهم؛ هر دانش‌آموز با یک یا چند مشاور اختصاصی، بدون گروه‌بندی کلاسی. اطلاعات این صفحه نمایشی است.',
            ],
            'advisor' => [
                'name' => 'آرزو بهرامی',
                'tagline' => 'مدیر تیم مشاوره آکادمی سلام',
                'bio' => 'تیم نمونه سلام با برنامه‌ریزی فردی، تحلیل آزمون و گفت‌وگوی هفتگی همراه دانش‌آموزان رشته‌های تجربی، ریاضی و انسانی است. این معرفی داده نمایشی سامانه است.',
            ],
            'stats' => ['items' => [
                ['value' => 12, 'suffix' => '', 'label' => 'دانش‌آموز نمونه', 'visible' => true],
                ['value' => 3, 'suffix' => '', 'label' => 'مشاور', 'visible' => true],
                ['value' => 3, 'suffix' => '', 'label' => 'پایه تحصیلی', 'visible' => true],
            ]],
        ],
    ],
];
