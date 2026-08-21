<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConsultantDashboardController extends Controller
{
    public function index(Request $request)
    {
        $assignedStudents = [
            [
                'id' => 1,
                'username' => 'علی رضایی',
                'email' => 'ali@example.com',
                'grade' => '12',
                'gender' => 'پسر',
                'major' => 'تجربی',
            ],
            [
                'id' => 2,
                'username' => 'سارا محمدی',
                'email' => 'sara@example.com',
                'grade' => '11',
                'gender' => 'دختر',
                'major' => 'ریاضی',
            ],
            [
                'id' => 3,
                'username' => 'محمد کریمی',
                'email' => 'mohammad@example.com',
                'grade' => '10',
                'gender' => 'پسر',
                'major' => 'انسانی',
            ],
            [
                'id' => 4,
                'username' => 'نگار احمدی',
                'email' => 'negar@example.com',
                'grade' => '12',
                'gender' => 'دختر',
                'major' => 'تجربی',
            ],
        ];

        $labels = [
            'dashboard_heading' => 'داشبورد مشاور',
            'welcome_prefix' => 'خوش آمدید',
            'create_post' => 'ایجاد پست',
            'student_count' => 'تعداد دانش‌آموزان',
            'active_quizzes' => 'آزمون‌های فعال',
            'filters_heading' => 'فیلتر دانش‌آموزان',
            'search_placeholder' => 'جستجوی دانش‌آموز...',
            'all_grades' => 'همه پایه‌ها',
            'all_majors' => 'همه رشته‌ها',
            'all_genders' => 'همه جنسیت‌ها',
            'sort_asc' => 'نام: صعودی',
            'sort_desc' => 'نام: نزولی',
            'student_list' => 'لیست دانش‌آموزان',
            'student_grade' => 'پایه',
            'student_major' => 'رشته',
            'schedule' => 'برنامه',
            'quizzes' => 'آزمون‌ها',
            'report_card' => 'کارنامه',
            'empty_students_title' => 'دانش‌آموزی وجود ندارد',
            'empty_students_text' => 'در حال حاضر دانش‌آموزی به شما اختصاص داده نشده است.',
            'empty_search_title' => 'نتیجه‌ای پیدا نشد',
            'empty_search_text' => 'دانش‌آموزی مطابق جستجوی شما پیدا نشد.',
        ];

        $filters = [
            'grades' => [
                ['value' => '10', 'label' => 'دهم'],
                ['value' => '11', 'label' => 'یازدهم'],
                ['value' => '12', 'label' => 'دوازدهم'],
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
        ];

        return view('consultant.dashboard', [
            'assigned_students' => $assignedStudents,
            'active_quizzes_count' => 7,
            'username' => session('username', 'مدیر سیستم'),
            'labels' => $labels,
            'filters' => $filters,
        ]);
    }
}