<?php

namespace App\Http\Requests\Consultant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for creating (and, via UpdateScheduleItemRequest,
 * editing) a consultant-authored schedule item.
 *
 * Deliberately does NOT accept raw start_datetime/end_datetime strings from
 * the client. Instead it accepts week_start_date + day_index + start_time +
 * end_time, and the controller derives the actual datetimes server-side.
 * This keeps date arithmetic in one place (PHP) instead of trusting
 * whatever the browser's timezone-sensitive JS computed.
 */
class StoreScheduleItemRequest extends FormRequest
{
   public function authorize(): bool
{
    return tenant() !== null;
}
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'week_start_date' => ['required', 'date_format:Y-m-d'],
            'day_index' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'color' => ['nullable', 'string', 'max:30'],
            'book_name' => ['nullable', 'string', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'test_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url', 'max:2083'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان برنامه الزامی است.',
            'day_index.required' => 'انتخاب روز هفته الزامی است.',
            'start_time.required' => 'ساعت شروع الزامی است.',
            'end_time.required' => 'ساعت پایان الزامی است.',
            'end_time.after' => 'ساعت پایان باید بعد از ساعت شروع باشد.',
            'link_url.url' => 'لینک وارد شده معتبر نیست.',
        ];
    }
}
