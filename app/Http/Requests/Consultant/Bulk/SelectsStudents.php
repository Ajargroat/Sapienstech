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
            // Multi-value fields (MULTI_FIELDS) arrive as "field[]" arrays from
            // the dashboard; scalar values stay accepted so older links and
            // tests keep working. StudentFilter::fromRequest whitelists every
            // value either way — these rules are only the request gate.
            'grade' => ['nullable'],
            'grade.*' => ['string', 'max:50'],
            'gender' => ['nullable'],
            'gender.*' => ['string', 'max:50'],
            'major' => ['nullable'],
            'major.*' => ['string', 'max:100'],
            // Domain filter pages of the dashboard carousel ride along on the
            // POST so server-side select_all re-derives the exact displayed
            // set.
            'exam_status' => ['nullable'],
            'exam_status.*' => ['string', 'max:50'],
            'exam_lesson' => ['nullable'],
            'exam_lesson.*' => ['string', 'max:100'],
            'exam_type' => ['nullable', 'string', 'max:50'],
            'report_source' => ['nullable'],
            'report_source.*' => ['string', 'max:100'],
            'report_status' => ['nullable'],
            'report_status.*' => ['string', 'max:50'],
            'schedule_day' => ['nullable'],
            'schedule_day.*' => ['string', 'max:2'],
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
