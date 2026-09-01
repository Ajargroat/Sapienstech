<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * PROTOTYPE: consultant-facing report-card (کارنامه) workspace for a single student.
 *
 * There is no ReportCard model / report_cards table yet, so the rows below are
 * hard-coded sample data. The point of this controller is to prove the routing +
 * view wiring (report-card.blade.php is only rendered because this controller
 * explicitly returns it -- a view file existing on disk never affects routing)
 * and to shape what the real feature will need: term switching, per-lesson
 * grades, descriptive evaluation, trend vs the previous term and a
 * send-to-parents flow.
 *
 * When the real domain lands, replace prototypeReportCards() with Eloquent
 * queries scoped to $student. Tenant isolation keeps working the same way:
 * {student} is implicitly bound through the BelongsToTenant global scope
 * (same pattern as StudentExamController).
 */
class StudentReportCardController extends Controller
{
    /**
     * Newest term first: array_key_first() doubles as the default term.
     */
    private const TERMS = [
        'nobat-dovom' => 'نوبت دوم ۱۴۰۳–۱۴۰۴',
        'nobat-aval' => 'نوبت اول ۱۴۰۳–۱۴۰۴',
    ];

    private const GRADES = [
        'excellent' => 'خیلی‌خوب',
        'good' => 'خوب',
        'ok' => 'قابل‌قبول',
        'effort' => 'نیاز به تلاش',
    ];

    public function index(Request $request, Student $student): View
    {
        $term = (string) $request->query('term', '');
        if (!array_key_exists($term, self::TERMS)) {
            $term = (string) array_key_first(self::TERMS);
        }

        $search = trim((string) $request->query('search', ''));

        $cards = $this->prototypeReportCards();

        $all = collect($cards[$term])->map(function (array $lesson) {
            $lesson['percent'] = round($lesson['total'] / 20 * 100);
            $lesson['grade'] = match (true) {
                $lesson['total'] >= 17 => 'excellent',
                $lesson['total'] >= 14 => 'good',
                $lesson['total'] >= 10 => 'ok',
                default => 'effort',
            };
            $lesson['trend'] = $lesson['previous'] === null
                ? null
                : round($lesson['total'] - $lesson['previous'], 1);

            return $lesson;
        });

        $lessons = $all
            ->when($search !== '', fn (Collection $q) => $q->filter(
                fn (array $lesson) => str_contains($lesson['lesson'], $search)
            ))
            ->values();

        $stats = [
            'average' => round((float) $all->avg('total'), 1),
            'lessons' => $all->count(),
            'absences' => (int) $all->sum('absences'),
            'needs_attention' => $all->filter(fn (array $lesson) => $lesson['total'] < 12)->count(),
        ];

        $termCounts = collect($cards)->map(fn (array $lessons) => count($lessons));

        return view('consultant.students.report-card', [
            'student' => $student,
            'lessons' => $lessons,
            'stats' => $stats,
            'terms' => self::TERMS,
            'term' => $term,
            'termCounts' => $termCounts,
            'grades' => self::GRADES,
            'search' => $search,
        ]);
    }

    /**
     * Sample report cards per term, standing in for the future report_cards
     * domain. `previous` is the same lesson's final score in the older term
     * (null for the oldest term) and drives the trend arrows.
     */
    private function prototypeReportCards(): array
    {
        return [
            'nobat-dovom' => [
                ['lesson' => 'زیست‌شناسی', 'continuous' => 18, 'midterm' => 17, 'final' => 18.5,
                    'total' => 18.2, 'absences' => 0, 'previous' => 16.5],
                ['lesson' => 'شیمی', 'continuous' => 16.5, 'midterm' => 15, 'final' => 16,
                    'total' => 15.8, 'absences' => 1, 'previous' => 15],
                ['lesson' => 'فیزیک', 'continuous' => 14, 'midterm' => 12.5, 'final' => 13.75,
                    'total' => 13.4, 'absences' => 2, 'previous' => 14.5],
                ['lesson' => 'ریاضی', 'continuous' => 12, 'midterm' => 10.5, 'final' => 11.25,
                    'total' => 11.2, 'absences' => 3, 'previous' => 10.8],
                ['lesson' => 'ادبیات فارسی', 'continuous' => 19, 'midterm' => 18, 'final' => 18.5,
                    'total' => 18.6, 'absences' => 0, 'previous' => 18],
                ['lesson' => 'عربی', 'continuous' => 15.5, 'midterm' => 14, 'final' => 15,
                    'total' => 14.8, 'absences' => 1, 'previous' => 13.5],
                ['lesson' => 'دین و زندگی', 'continuous' => 17.5, 'midterm' => 17, 'final' => 18,
                    'total' => 17.6, 'absences' => 0, 'previous' => 17.6],
                ['lesson' => 'زبان انگلیسی', 'continuous' => 13, 'midterm' => 15, 'final' => 14,
                    'total' => 14.1, 'absences' => 0, 'previous' => 12],
            ],
            'nobat-aval' => [
                ['lesson' => 'زیست‌شناسی', 'continuous' => 16, 'midterm' => 17, 'final' => 16.5,
                    'total' => 16.5, 'absences' => 1, 'previous' => null],
                ['lesson' => 'شیمی', 'continuous' => 15, 'midterm' => 14.5, 'final' => 15.5,
                    'total' => 15, 'absences' => 0, 'previous' => null],
                ['lesson' => 'فیزیک', 'continuous' => 15, 'midterm' => 14, 'final' => 14.5,
                    'total' => 14.5, 'absences' => 1, 'previous' => null],
                ['lesson' => 'ریاضی', 'continuous' => 11, 'midterm' => 10, 'final' => 11.5,
                    'total' => 10.8, 'absences' => 2, 'previous' => null],
                ['lesson' => 'ادبیات فارسی', 'continuous' => 18, 'midterm' => 17.5, 'final' => 18.5,
                    'total' => 18, 'absences' => 0, 'previous' => null],
                ['lesson' => 'عربی', 'continuous' => 13, 'midterm' => 13.5, 'final' => 14,
                    'total' => 13.5, 'absences' => 1, 'previous' => null],
                ['lesson' => 'دین و زندگی', 'continuous' => 17, 'midterm' => 17.5, 'final' => 18,
                    'total' => 17.6, 'absences' => 0, 'previous' => null],
                ['lesson' => 'زبان انگلیسی', 'continuous' => 12, 'midterm' => 11.5, 'final' => 12.5,
                    'total' => 12, 'absences' => 0, 'previous' => null],
            ],
        ];
    }
}
