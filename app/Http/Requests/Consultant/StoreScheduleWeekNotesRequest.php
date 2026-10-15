<?php

namespace App\Http\Requests\Consultant;

use App\Models\ScheduleWeekNote;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The weekly-notes payload: a whole week at a time.
 *
 * Notes are saved as one map (day_index => text) rather than one request per
 * day, because that is how they are edited -- the consultant opens "توضیحات
 * هفته", rewrites several days and saves once. A day missing from the map keeps
 * whatever it already had; a day sent as an empty string is a deliberate
 * deletion and drops its row.
 *
 * week_start_date comes from the client's current grid (the same value every
 * other schedule mutation sends); the controller re-derives the week from it
 * with resolveWeekStart() rather than trusting the string as-is.
 */
class StoreScheduleWeekNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null;
    }

    public function rules(): array
    {
        return [
            'week_start_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['present', 'array', 'max:7'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'week_start_date.required' => 'هفته انتخاب‌شده نامعتبر است.',
            'notes.max' => 'حداکثر هفت روز در هفته قابل ثبت است.',
            'notes.*.max' => 'متن هر روز حداکثر ۲۰۰۰ نویسه می‌تواند باشد.',
        ];
    }

    /**
     * The submitted map, keyed by a validated 0..6 day index.
     *
     * @return array<int, string|null>
     */
    public function notesByDay(): array
    {
        $out = [];

        foreach ($this->validated('notes', []) as $day => $text) {
            if (! is_numeric($day)) {
                continue;
            }

            $index = (int) $day;

            if ($index < 0 || $index > 6 || ! isset(ScheduleWeekNote::DAYS[$index])) {
                continue;
            }

            $out[$index] = $text === null ? null : trim((string) $text);
        }

        return $out;
    }
}
