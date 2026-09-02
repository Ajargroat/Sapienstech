<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
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
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $grade = trim((string) $request->query('grade', ''));
        $gender = trim((string) $request->query('gender', ''));
        $major = trim((string) $request->query('major', ''));
        $sort = (string) $request->query('sort', '');

        $allowedSorts = ['name_asc', 'name_desc', 'newest', 'oldest'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = '';
        }

        $distinctOptions = function (string $column) {
            return Student::query()
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->orderBy($column)
                ->pluck($column);
        };

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($grade !== '', fn ($query) => $query->where('grade', $grade))
            ->when($gender !== '', fn ($query) => $query->where('gender', $gender))
            ->when($major !== '', fn ($query) => $query->where('major', $major))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name'))
            ->when($sort === 'newest', fn ($query) => $query->orderByDesc('created_at'))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('created_at'))
            ->when($sort === '' || $sort === 'name_asc', fn ($query) => $query->orderBy('name'))
            ->paginate(15)
            ->withQueryString();

        $activeFilterCount = count(array_filter([$grade, $gender, $major, $sort]));

        return view('consultant.dashboard', [
            'students' => $students,
            'search' => $search,
            'username' => session('username', 'مدیر سیستم'),
            'gradeOptions' => $distinctOptions('grade'),
            'genderOptions' => $distinctOptions('gender'),
            'majorOptions' => $distinctOptions('major'),
            'filters' => [
                'grade' => $grade,
                'gender' => $gender,
                'major' => $major,
                'sort' => $sort,
            ],
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
