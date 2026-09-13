<?php

namespace App\Http\Requests\Consultant\Bulk;

/**
 * Shared selection rules for every bulk flow: either explicit student ids or
 * select_all + the filter set (hidden fields posted back by the picker).
 */
trait SelectsStudents
{
    protected function selectionRules(): array
    {
        return [
            'select_all' => ['nullable', 'boolean'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:50'],
            'major' => ['nullable', 'string', 'max:100'],
            // Domain filter pages of the dashboard carousel ride along on the
            // POST so server-side select_all re-derives the exact displayed
            // set; StudentFilter::fromRequest whitelists every value anyway.
            'exam_status' => ['nullable', 'string', 'max:50'],
            'exam_lesson' => ['nullable', 'string', 'max:100'],
            'exam_type' => ['nullable', 'string', 'max:50'],
            'report_source' => ['nullable', 'string', 'max:100'],
            'report_status' => ['nullable', 'string', 'max:50'],
            'schedule_day' => ['nullable', 'string', 'max:2'],
            'schedule_done' => ['nullable', 'string', 'max:10'],
            'filter_open' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function selectionMessages(): array
    {
        return [
            'test_id.required' => 'انتخاب آزمون الزامی است.',
            'test_id.exists' => 'آزمون انتخابی یافت نشد.',
            'title.required' => 'عنوان بلوک برنامه الزامی است.',
            'week_start_date.required' => 'تاریخ شروع هفته الزامی است.',
            'day_index.required' => 'انتخاب روز هفته الزامی است.',
            'start_time.required' => 'ساعت شروع الزامی است.',
            'end_time.required' => 'ساعت پایان الزامی است.',
            'end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد.',
        ];
    }
}
