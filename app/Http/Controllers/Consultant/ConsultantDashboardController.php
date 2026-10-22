<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\DealPayment;
use App\Models\ExamCompany;
use App\Models\Student;
use App\Models\StudentDeal;
use App\Models\StudentJoinRequest;
use App\Models\Test;
use App\Support\StudentFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ConsultantDashboardController extends Controller
{
    /**
     * The dashboard IS the student workspace: it lists the current tenant's
     * students, searchable and filterable by their details, server-side
     * paginated.
     *
     * The filter popover is a four-page carousel; every page both narrows the
     * student list AND (for exams/schedule) posts a bulk assignment that
     * applies to the whole current filter set. Assignment targets are
     * re-derived server-side via StudentFilter/BulkSelection by the bulk
     * endpoints, so a panel's assignment always covers exactly what the
     * student grid showed.
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

        $students = StudentFilter::apply(Student::query(), $filters)
            ->paginate(15)
            ->withQueryString();

        $activeFilterCount = StudentFilter::activeCount($filters);

        // The report-card source vocabulary: every company the tenant knows
        // plus the internal bucket, mirroring StudentReportCardController.
        $reportSources = ExamCompany::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ExamCompany $company) => [$company->slug => $company->name])
            ->put(StudentFilter::REPORT_INTERNAL, StudentFilter::REPORT_INTERNAL_LABEL);

        $optionCounts = StudentFilter::optionCounts();
        $weekStart = $this->persianWeekStart();

        // Consultant-facing rollup: the roster count plus the three queues a
        // consultant actually works — join requests, live contracts and
        // receipts waiting for verification. Tenant scope comes from each
        // model's BelongsToTenant, exactly like the roster grid.
        $pendingRequests = StudentJoinRequest::query()
            ->where('status', StudentJoinRequest::STATUS_PENDING)
            ->count();

        $activeDeals = StudentDeal::query()
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->count();

        $pendingPayments = DealPayment::query()
            ->where('status', DealPayment::STATUS_PENDING)
            ->count();

        $totalStudents = (int) ($optionCounts['total'] ?? 0);

        $gradeBars = collect($optionCounts['grade'] ?? [])
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $grade) => [
                'label' => (string) $grade,
                'count' => (int) $count,
                'percent' => $totalStudents > 0
                    ? (int) round(((int) $count / $totalStudents) * 100)
                    : 0,
            ])
            ->values()
            ->all();

        return view('consultant.dashboard', [
            'students' => $students,
            'search' => $filters['search'],
            'username' => session('username', 'مدیر سیستم'),
            'gradeOptions' => StudentFilter::distinctOptions('grade'),
            'genderOptions' => StudentFilter::distinctOptions('gender'),
            'majorOptions' => StudentFilter::distinctOptions('major'),
            'filters' => $filters,
            'activeFilterCount' => $activeFilterCount,
            'examStatuses' => StudentFilter::EXAM_STATUSES,
            'examTypes' => StudentFilter::EXAM_TYPES,
            // Lessons the tenant's own tests actually cover (tests store
            // joined lessons as one string), with per-option student counts
            // for the report-card-style menus.
            'examLessons' => StudentFilter::examLessons()->all(),
            'optionCounts' => $optionCounts,
            'reportStatuses' => StudentFilter::REPORT_STATUSES,
            'reportSources' => $reportSources,
            'scheduleDays' => StudentFilter::SCHEDULE_DAYS,
            'scheduleDoneOptions' => StudentFilter::SCHEDULE_DONE_OPTIONS,
            // Exam assignment (bulk engine): pick an existing tenant test.
            'tests' => Test::query()->latest()->get(['id', 'test_title', 'lesson', 'exam_type']),
            'weekStart' => $weekStart->toDateString(),
            'dashboardStats' => [
                'students_total' => $totalStudents,
                'students_new_30' => Student::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'pending_requests' => $pendingRequests,
                'active_deals' => $activeDeals,
                'pending_payments' => $pendingPayments,
            ],
            'gradeBars' => $gradeBars,
            'recentStudents' => StudentFilter::apply(Student::query(), $filters)
                ->reorder()
                ->latest()
                ->take(4)
                ->get(['id', 'name', 'grade', 'major', 'email']),
            // Panels mirror the student workspaces; the assignment sections
            // additionally need the bulk_actions flag (they post to it).
            'panelFlags' => [
                'exams' => (bool) site('features.student_exams', false),
                'reports' => (bool) site('features.report_cards', false),
                'schedule' => (bool) site('features.student_schedule', false),
                'assign' => (bool) site('features.bulk_actions', false),
            ],
            'labels' => [
                'dashboard_heading' => 'داشبورد مشاور',
                'welcome_prefix' => 'خوش آمدید',
                'overview_subtitle' => 'یک نگاه سریع به وضعیت دانش‌آموزان، برنامهٔ این هفته و آزمون‌های پیش‌رو.',
                'stat_students' => 'دانش‌آموزان',
                'stat_students_meta' => 'نفر تازه در ۳۰ روز اخیر',
                'stat_requests' => 'درخواست‌های عضویت',
                'stat_requests_meta' => 'در انتظار بررسی',
                'stat_deals' => 'قراردادهای جاری',
                'stat_deals_meta' => 'قرارداد فعال',
                'stat_payments' => 'پرداخت‌های در انتظار',
                'stat_payments_meta' => 'رسید در انتظار تأیید',
                'week_progress' => 'پیشرفت این هفته',
                'grade_mix' => 'ترکیب دانش‌آموزان',
                'grade_mix_empty' => 'برای نمایش ترکیب، ابتدا پایهٔ دانش‌آموزان را ثبت کنید.',
                'recent_students' => 'تازه‌ترین دانش‌آموزان',
                'recent_students_empty' => 'هنوز دانش‌آموزی در این مجموعه ثبت نشده است.',
                'quick_actions' => 'دسترسی سریع',
                'action_add_student' => 'افزودن دانش‌آموز',
                'action_requests' => 'درخواست‌ها',
                'action_deals' => 'تمدیدها',
                'action_chat' => 'گفتگو',
                'action_studio' => 'استودیوی ظاهر',
                'view_all' => 'مشاهدهٔ همه',
                'today_label' => 'امروز',
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
                'filter_selected' => 'مورد',
                'sort_name_asc' => 'نام (الف تا ی)',
                'sort_name_desc' => 'نام (ی تا الف)',
                'sort_newest' => 'جدیدترین',
                'sort_oldest' => 'قدیمی‌ترین',
                'panel_exams' => 'آزمون',
                'panel_reports' => 'کارنامه',
                'panel_schedule' => 'برنامه هفتگی',
                'panel_disabled' => 'این بخش برای مجموعه شما غیرفعال است.',
                'filter_exam_status' => 'وضعیت آزمون',
                'filter_exam_lesson' => 'درس آزمون',
                'filter_exam_type' => 'نوع آزمون',
                'filter_report_source' => 'منبع کارنامه',
                'filter_report_status' => 'وضعیت کارنامه',
                'filter_schedule_day' => 'روز هفته',
                'filter_schedule_done' => 'وضعیت انجام',
                'assign_exam_title' => 'واگذاری گروهی آزمون',
                'assign_exam_hint' => 'آزمون انتخابی به همهٔ دانش‌آموزان مطابق فیلترهای فعال واگذار می‌شود.',
                'assign_exam_none' => 'هنوز آزمونی ساخته نشده است. ابتدا از مسیر دانش‌آموز یک آزمون بسازید.',
                'assign_exam_test' => 'آزمون',
                'assign_exam_date' => 'زمان برگزاری (اختیاری)',
                'assign_exam_submit' => 'واگذاری گروهی',
                'assign_schedule_title' => 'ساخت گروهی بلوک هفتگی',
                'assign_schedule_hint' => 'بلوک جدید برای همهٔ دانش‌آموزان مطابق فیلترهای فعال ثبت می‌شود.',
                'assign_schedule_submit' => 'ثبت گروهی برنامه',
                'assign_schedule_block_title' => 'عنوان بلوک',
                'assign_schedule_week_start' => 'هفتهٔ شروع',
                'assign_schedule_start' => 'شروع',
                'assign_schedule_end' => 'پایان',
                'assign_schedule_color' => 'رنگ',
                'assign_schedule_book' => 'کتاب',
                'assign_schedule_test_count' => 'تعداد تست',
                'assign_schedule_page_count' => 'تعداد صفحه',
                'to_filtered_students' => 'نفر',
                'filter_history' => 'تاریخچهٔ اقدامات گروهی',
            ],
        ]);
    }

    /** Today's Saturday-anchored (Persian) week start, mirroring the bulk flows. */
    private function persianWeekStart(): Carbon
    {
        $today = Carbon::today();

        // Carbon::dayOfWeek is 0=Sun..6=Sat; the Persian week starts Saturday.
        return $today->subDays(($today->dayOfWeek + 1) % 7);
    }
}
