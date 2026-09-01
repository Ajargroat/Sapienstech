<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * PROTOTYPE: consultant-facing exams workspace for a single student.
 *
 * There is no Exam model / exams table yet, so the exam rows below are
 * hard-coded sample data. The point of this controller is to prove the
 * routing + view wiring (the exams.blade.php view is only rendered because
 * this controller explicitly returns it -- a view file existing on disk
 * never affects routing) and to shape what the real feature will need:
 * summary stats, status filtering, search, a results table and a
 * create-exam flow.
 *
 * When the real domain lands, replace prototypeExams() with an Eloquent
 * query scoped to $student. Tenant isolation keeps working the same way:
 * {student} is implicitly bound through the BelongsToTenant global scope
 * (same pattern as StudentScheduleController::edit()).
 */
class StudentExamController extends Controller
{
    private const STATUSES = [
        'completed' => 'انجام‌شده',
        'grading' => 'در حال تصحیح',
        'scheduled' => 'برگزارنشده',
        'missed' => 'غیبت',
    ];

    public function index(Request $request, Student $student): View
    {
        $status = (string) $request->query('status', '');
        if ($status !== '' && !array_key_exists($status, self::STATUSES)) {
            $status = '';
        }

        $search = trim((string) $request->query('search', ''));

        $all = collect($this->prototypeExams());

        $exams = $all
            ->when($status !== '', fn (Collection $q) => $q->where('status', $status))
            ->when($search !== '', fn (Collection $q) => $q->filter(
                fn (array $exam) => str_contains($exam['title'], $search)
                    || str_contains($exam['lesson'], $search)
            ))
            ->values();

        $completed = $all->where('status', 'completed');

        $stats = [
            'total' => $all->count(),
            'completed' => $completed->count(),
            'scheduled' => $all->where('status', 'scheduled')->count(),
            'average_percent' => $completed->isNotEmpty()
                ? round($completed->avg(fn (array $exam) => $exam['score'] / $exam['total'] * 100))
                : null,
        ];

        $statusCounts = $all
            ->groupBy('status')
            ->map(fn (Collection $group) => $group->count());

        return view('consultant.students.exams', [
            'student' => $student,
            'exams' => $exams,
            'stats' => $stats,
            'statuses' => self::STATUSES,
            'statusCounts' => $statusCounts,
            'status' => $status,
            'search' => $search,
        ]);
    }

    /**
     * Sample exam rows standing in for the future exams table.
     */
    private function prototypeExams(): array
    {
        return [
            ['id' => 1, 'title' => 'آزمون جامع ریاضیات — نوبت اول', 'lesson' => 'ریاضی',
                'date' => '۱۴۰۴/۰۴/۱۵', 'time' => '۱۰:۰۰', 'duration' => 90,
                'score' => 17.5, 'total' => 20, 'status' => 'completed'],
            ['id' => 2, 'title' => 'آزمون فصلی فیزیک (حرکت‌شناسی)', 'lesson' => 'فیزیک',
                'date' => '۱۴۰۴/۰۵/۰۲', 'time' => '۰۹:۰۰', 'duration' => 60,
                'score' => 12.25, 'total' => 20, 'status' => 'completed'],
            ['id' => 3, 'title' => 'آزمون آزمایشی ۱۲ — شبیه‌سازی کنکور', 'lesson' => 'علوم تجربی + ریاضی',
                'date' => '۱۴۰۴/۰۵/۲۰', 'time' => '۰۸:۰۰', 'duration' => 180,
                'score' => 68, 'total' => 100, 'status' => 'completed'],
            ['id' => 4, 'title' => 'کوئیز آنلاین شیمی (دوره‌های عناصر)', 'lesson' => 'شیمی',
                'date' => '۱۴۰۴/۰۶/۰۱', 'time' => '۱۸:۰۰', 'duration' => 30,
                'score' => null, 'total' => 10, 'status' => 'grading'],
            ['id' => 5, 'title' => 'امتحان میان‌ترم ادبیات فارسی', 'lesson' => 'ادبیات فارسی',
                'date' => '۱۴۰۴/۰۶/۱۰', 'time' => '۱۱:۰۰', 'duration' => 75,
                'score' => null, 'total' => 20, 'status' => 'missed'],
            ['id' => 6, 'title' => 'آزمون جامع زیست‌شناسی (سلول و ژنتیک)', 'lesson' => 'زیست‌شناسی',
                'date' => '۱۴۰۴/۰۶/۲۸', 'time' => '۱۰:۳۰', 'duration' => 90,
                'score' => null, 'total' => 20, 'status' => 'scheduled'],
            ['id' => 7, 'title' => 'آزمون آزمایشی ۱۳ — کنکور سراسری', 'lesson' => 'همه دروس',
                'date' => '۱۴۰۴/۰۷/۰۵', 'time' => '۰۸:۰۰', 'duration' => 240,
                'score' => null, 'total' => 100, 'status' => 'scheduled'],
        ];
    }
}
