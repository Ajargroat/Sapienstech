<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Support\StudentAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's class timetable: the weekly blocks published for their
 * grade (or school-wide), laid out day by day over the Persian week.
 */
class StudentTimetableController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student');

        $teacherAccess = [];
        $items = ClassSchedule::query()
            ->where('tenant_id', $student->tenant_id)
            ->visibleTo($student)
            ->orderBy('start_time')
            ->with('teacher')
            ->get()
            ->filter(function (ClassSchedule $item) use ($student, &$teacherAccess) {
                return $teacherAccess[$item->teacher_id] ??= $item->teacher
                    && StudentAccess::allows($item->teacher, $student);
            })
            ->groupBy('day_of_week');

        $persianDay = (int) ((now()->dayOfWeek + 1) % 7);

        return view('student.timetable', [
            'student' => $student,
            'items' => $items,
            'dayLabels' => ClassSchedule::DAY_LABELS,
            'todayDay' => $persianDay,
        ]);
    }
}
