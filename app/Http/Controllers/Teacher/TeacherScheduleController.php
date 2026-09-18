<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Support\Academics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The teacher's class timetable: recurring weekly blocks (Saturday-anchored
 * Persian week). Server-rendered CRUD with a <dialog> editor — the same
 * week convention (0 = Saturday) the consultant schedule uses, but no
 * drag-grid, since classes repeat weekly rather than once.
 */
class TeacherScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $teacher = $request->user();

        $items = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        $persianDay = (int) ((now()->dayOfWeek + 1) % 7);

        return view('teacher.schedule', [
            'items' => $items,
            'dayLabels' => ClassSchedule::DAY_LABELS,
            'todayDay' => $persianDay,
            'gradeOptions' => Academics::gradeOptions(),
            'subjects' => Academics::subjects(),
            'editing' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $request->user()->classSchedules()->create($data);

        return redirect()
            ->route('teacher.schedule.index')
            ->with('success', 'زنگ کلاس اضافه شد.');
    }

    public function update(Request $request, ClassSchedule $item)
    {
        $teacher = $request->user();

        abort_unless($item->teacher_id === $teacher->id, 404);

        $item->update($this->validated($request));

        return redirect()
            ->route('teacher.schedule.index')
            ->with('success', 'زنگ کلاس به‌روزرسانی شد.');
    }

    public function destroy(Request $request, ClassSchedule $item)
    {
        $teacher = $request->user();

        abort_unless($item->teacher_id === $teacher->id, 404);

        $item->delete();

        return redirect()
            ->route('teacher.schedule.index')
            ->with('success', 'زنگ کلاس حذف شد.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'subject' => ['nullable', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:50'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $data['is_published'] = $request->boolean('is_published', true);

        return $data;
    }
}
