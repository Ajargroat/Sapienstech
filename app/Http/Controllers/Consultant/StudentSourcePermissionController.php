<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * PROTOTYPE: consultant-facing source-permission workspace for a single student.
 *
 * Domain idea: study access and exam access are separate grants. A consultant
 * can give the student book A to study from while testing them from bank B, so
 * exam questions stay fresh. There is no Source/Grant model yet, so the rows
 * below are hard-coded sample data. The point of this controller is to prove
 * the routing + view wiring (source-permissions.blade.php is only rendered
 * because this controller explicitly returns it -- a view file existing on
 * disk never affects routing) and to shape what the real feature will need:
 * a grant list with status filtering and search, per-lesson study/assessment
 * pairings with a freshness check, and a grant-access dialog.
 *
 * Tenant isolation keeps working the same way as StudentExamController:
 * {student} is implicitly bound through the BelongsToTenant global scope.
 */
class StudentSourcePermissionController extends Controller
{
    private const STATUSES = [
        'active' => 'فعال',
        'expiring' => 'رو به اتمام',
        'expired' => 'منقضی‌شده',
        'paused' => 'متوقف‌شده',
    ];

    private const KINDS = [
        'study' => 'مطالعه',
        'exam' => 'سنجش',
    ];

    /**
     * Pairing freshness states shown in the study/assessment table.
     */
    private const STATES = [
        'fresh' => 'سوالات تازه',
        'overlap' => 'هم‌پوشانی منبع',
        'missing' => 'جفت ناقص',
    ];

    /**
     * Stand-in for the future sources catalog feeding the grant dialog.
     */
    private const SOURCE_CATALOG = [
        'کتاب جامع زیست‌شناسی قلم‌چی',
        'کتاب شیمی آبی‌رنگ',
        'کتاب ریاضی ۱ کنکور',
        'کتاب فیزیک هلیکس',
        'بانک سوال زیست — نوبت دوم',
        'بانک تست ریاضی — مجموعه دوم',
        'بسته آزمون‌های آزمایشی ۱۲ و ۱۳',
    ];

    public function index(Request $request, Student $student): View
    {
        $status = (string) $request->query('status', '');
        if ($status !== '' && !array_key_exists($status, self::STATUSES)) {
            $status = '';
        }

        $search = trim((string) $request->query('search', ''));

        $all = collect($this->prototypeGrants());

        $grants = $all
            ->when($status !== '', fn (Collection $q) => $q->where('status', $status))
            ->when($search !== '', fn (Collection $q) => $q->filter(
                fn (array $grant) => str_contains($grant['source'], $search)
                    || str_contains($grant['lesson'], $search)
            ))
            ->values();

        $stats = [
            'total' => $all->count(),
            'active' => $all->where('status', 'active')->count(),
            'study' => $all->where('kind', 'study')->count(),
            'exam' => $all->where('kind', 'exam')->count(),
            'expiring' => $all->where('status', 'expiring')->count(),
        ];

        $statusCounts = $all
            ->groupBy('status')
            ->map(fn (Collection $group) => $group->count());

        // Per-lesson study/assessment pairing. `overlap` means the exam draws
        // from the same book the student studies (questions are no longer
        // fresh); a null side means the pair is incomplete.
        $pairings = collect($this->prototypePairings())->map(function (array $pair) {
            $pair['state'] = match (true) {
                $pair['study'] === null || $pair['exam'] === null => 'missing',
                $pair['overlap'] => 'overlap',
                default => 'fresh',
            };

            return $pair;
        });

        return view('consultant.students.source-permissions', [
            'student' => $student,
            'grants' => $grants,
            'pairings' => $pairings,
            'stats' => $stats,
            'statuses' => self::STATUSES,
            'statusCounts' => $statusCounts,
            'status' => $status,
            'kinds' => self::KINDS,
            'states' => self::STATES,
            'sources' => self::SOURCE_CATALOG,
            'search' => $search,
        ]);
    }

    /**
     * Sample access grants standing in for the future student_source_grants
     * domain: each row is one source, granted for one purpose (study or exam).
     */
    private function prototypeGrants(): array
    {
        return [
            ['id' => 1, 'source' => 'کتاب جامع زیست‌شناسی قلم‌چی', 'lesson' => 'زیست‌شناسی', 'type' => 'کتاب',
                'kind' => 'study', 'status' => 'active', 'from' => '۱۴۰۴/۰۳/۰۱', 'until' => '۱۴۰۴/۰۹/۳۰', 'progress' => 72],
            ['id' => 2, 'source' => 'بانک سوال زیست — نوبت دوم', 'lesson' => 'زیست‌شناسی', 'type' => 'بانک سوال',
                'kind' => 'exam', 'status' => 'active', 'from' => '۱۴۰۴/۰۵/۰۱', 'until' => '۱۴۰۴/۰۷/۳۰', 'progress' => 40],
            ['id' => 3, 'source' => 'کتاب شیمی آبی‌رنگ', 'lesson' => 'شیمی', 'type' => 'کتاب',
                'kind' => 'study', 'status' => 'active', 'from' => '۱۴۰۴/۰۳/۱۵', 'until' => '۱۴۰۴/۰۸/۱۵', 'progress' => 45],
            ['id' => 4, 'source' => 'آزمون‌های فصلی شیمی', 'lesson' => 'شیمی', 'type' => 'بانک سوال',
                'kind' => 'exam', 'status' => 'expiring', 'from' => '۱۴۰۴/۰۴/۰۱', 'until' => '۱۴۰۴/۰۶/۱۰', 'progress' => 80],
            ['id' => 5, 'source' => 'کتاب ریاضی ۱ کنکور', 'lesson' => 'ریاضی', 'type' => 'کتاب',
                'kind' => 'study', 'status' => 'expiring', 'from' => '۱۴۰۴/۰۲/۰۱', 'until' => '۱۴۰۴/۰۶/۰۵', 'progress' => 91],
            ['id' => 6, 'source' => 'بانک تست ریاضی — مجموعه دوم', 'lesson' => 'ریاضی', 'type' => 'بانک سوال',
                'kind' => 'exam', 'status' => 'active', 'from' => '۱۴۰۴/۰۵/۲۰', 'until' => '۱۴۰۴/۰۷/۲۰', 'progress' => 15],
            ['id' => 7, 'source' => 'کتاب فیزیک هلیکس', 'lesson' => 'فیزیک', 'type' => 'کتاب',
                'kind' => 'study', 'status' => 'paused', 'from' => '۱۴۰۴/۰۳/۰۱', 'until' => '۱۴۰۴/۱۰/۰۱', 'progress' => 23],
            ['id' => 8, 'source' => 'تست‌های پایان‌فصل هلیکس', 'lesson' => 'فیزیک', 'type' => 'بانک سوال',
                'kind' => 'exam', 'status' => 'active', 'from' => '۱۴۰۴/۰۴/۱۰', 'until' => '۱۴۰۴/۰۸/۱۰', 'progress' => 62],
            ['id' => 9, 'source' => 'کتاب ادبیات پایه (دهم)', 'lesson' => 'ادبیات فارسی', 'type' => 'کتاب',
                'kind' => 'study', 'status' => 'expired', 'from' => '۱۴۰۳/۰۹/۰۱', 'until' => '۱۴۰۴/۰۲/۲۸', 'progress' => 100],
            ['id' => 10, 'source' => 'بانک سوال عربی — گرامر', 'lesson' => 'عربی', 'type' => 'بانک سوال',
                'kind' => 'exam', 'status' => 'active', 'from' => '۱۴۰۴/۰۵/۰۵', 'until' => '۱۴۰۴/۰۶/۲۵', 'progress' => 55],
        ];
    }

    /**
     * Sample study/assessment pairings per lesson. فیزیک intentionally
     * overlaps (هلیکس studied and tested), ادبیات has no exam source and
     * عربی has no study source, so all three freshness states are visible.
     */
    private function prototypePairings(): array
    {
        return [
            ['lesson' => 'زیست‌شناسی', 'study' => 'کتاب جامع زیست‌شناسی قلم‌چی',
                'exam' => 'بانک سوال زیست — نوبت دوم', 'overlap' => false],
            ['lesson' => 'شیمی', 'study' => 'کتاب شیمی آبی‌رنگ',
                'exam' => 'آزمون‌های فصلی شیمی', 'overlap' => false],
            ['lesson' => 'ریاضی', 'study' => 'کتاب ریاضی ۱ کنکور',
                'exam' => 'بانک تست ریاضی — مجموعه دوم', 'overlap' => false],
            ['lesson' => 'فیزیک', 'study' => 'کتاب فیزیک هلیکس',
                'exam' => 'تست‌های پایان‌فصل هلیکس', 'overlap' => true],
            ['lesson' => 'عربی', 'study' => null,
                'exam' => 'بانک سوال عربی — گرامر', 'overlap' => false],
            ['lesson' => 'ادبیات فارسی', 'study' => 'کتاب ادبیات پایه (دهم)',
                'exam' => null, 'overlap' => false],
        ];
    }
}
