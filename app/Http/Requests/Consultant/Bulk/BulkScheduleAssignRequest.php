<?php

namespace App\Http\Requests\Consultant\Bulk;

use Illuminate\Foundation\Http\FormRequest;

class BulkScheduleAssignRequest extends FormRequest
{
    use SelectsStudents;

    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        return array_merge($this->selectionRules(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'week_start_date' => ['required', 'date_format:Y-m-d'],
            'day_index' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'color' => ['nullable', 'string', 'max:30'],
            'book_name' => ['nullable', 'string', 'max:255'],
            'page_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'test_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'link_url' => ['nullable', 'url', 'max:2083'],
        ]);
    }

    public function messages(): array
    {
        return $this->selectionMessages();
    }
}
