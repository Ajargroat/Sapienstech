<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\StudentFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultantDashboardController extends Controller
{
    /**
     * The dashboard IS the student workspace: it lists the current tenant's
     * students, searchable and filterable by their details, server-side
     * paginated.
     *
     * Tenant isolation is handled entirely by Student::BelongsToTenant
     * (global scope) -- this controller never touches tenant_id directly,
     * and never trusts a client-supplied tenant id. The distinct option
     * lists below run through the same global scope, so a consultant only
     * ever sees values that exist among their own students.
     *
     * Filter interpretation lives in App\Support\StudentFilter so the bulk
     * actions picker applies the exact same semantics.
     */
    public function index(Request $request): View
    {
        $filters = StudentFilter::fromRequest($request);

        $distinctOptions = function (string $column) {
            return Student::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column);
        };

        $students = StudentFilter::apply(Student::query(), $filters)
            ->paginate(15)
            ->withQueryString();

        $activeFilterCount = StudentFilter::activeCount($filters);

        return view('consultant.dashboard', [
            'students' => $students,
            'search' => $filters['search'],
            'username' => session('username', 'مدیر سیستم'),
            'gradeOptions' => $distinctOptions('grade'),
            'genderOptions' => $distinctOptions('gender'),
            'majorOptions' => $distinctOptions('major'),
            'filters' => $filters,
            'activeFilterCount' => $activeFilterCount,
            'labels' => [
                'dashboard_heading' => 'داشبورد مشاور',
                'welcome_prefix' => 'خوش آمدید',
                'student_list' => 'لیست دانش‌آموزان شما',
                'search_placeholder' => 'جستجو بر اساس نام یا ایمیل...',
                'search_button' => 'جستجو',
                'clear_search' => 'پاک کردن جستجو',
                'action_profile' => 'پروفایل دانش‌آموز',
                'empty_students_title' => 'دانش‌آموزی وجود ندارد',
                'empty_students_text' => 'در حال حاضر دانش‌آموزی برای این مجموعه ثبت نشده است.',
                'empty_search_title' => 'نتیجه‌ای پیدا نشد',
                'empty_search_text' => 'دانش‌آموزی مطابق جستجوی شما پیدا نشد.',
                'filter_button' => 'فیلتر و مرتب‌سازی',
                'filter_title' => 'فیلتر دانش‌آموزان',
                'filter_grade' => 'پایه تحصیلی',
                'filter_gender' => 'جنسیت',
                'filter_major' => 'رشته تحصیلی',
                'filter_sort' => 'مرتب‌سازی',
                'filter_all' => 'همه',
                'filter_apply' => 'اعمال',
                'filter_reset' => 'پاک کردن',
                'sort_name_asc' => 'نام (الف تا ی)',
                'sort_name_desc' => 'نام (ی تا الف)',
                'sort_newest' => 'جدیدترین',
                'sort_oldest' => 'قدیمی‌ترین',
            ],
        ]);
    }
}
