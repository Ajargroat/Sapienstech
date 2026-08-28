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
     * students, searchable by name/email, server-side paginated.
     *
     * Tenant isolation is handled entirely by Student::BelongsToTenant
     * (global scope) -- this controller never touches tenant_id directly,
     * and never trusts a client-supplied tenant id.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('consultant.dashboard', [
            'students' => $students,
            'search' => $search,
            'username' => session('username', 'مدیر سیستم'),
            'labels' => [
                'dashboard_heading' => 'داشبورد مشاور',
                'welcome_prefix' => 'خوش آمدید',
                'student_list' => 'لیست دانش‌آموزان شما',
                'search_placeholder' => 'جستجو بر اساس نام یا ایمیل...',
                'search_button' => 'جستجو',
                'clear_search' => 'پاک کردن جستجو',
                'th_name' => 'نام',
                'th_email' => 'ایمیل',
                'th_grade' => 'پایه',
                'th_gender' => 'جنسیت',
                'th_major' => 'رشته',
                'th_actions' => 'عملیات',
                'actions_label' => 'عملیات',
                'action_profile' => 'پروفایل دانش‌آموز',
                'action_report_card' => 'کارنامه',
                'action_exams' => 'آزمون‌ها',
                'action_schedule' => 'برنامه',
                'action_source_permissions' => 'دسترسی منابع',
                'empty_students_title' => 'دانش‌آموزی وجود ندارد',
                'empty_students_text' => 'در حال حاضر دانش‌آموزی برای این مجموعه ثبت نشده است.',
                'empty_search_title' => 'نتیجه‌ای پیدا نشد',
                'empty_search_text' => 'دانش‌آموزی مطابق جستجوی شما پیدا نشد.',
            ],
        ]);
    }
}
