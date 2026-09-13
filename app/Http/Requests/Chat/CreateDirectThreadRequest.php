<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class CreateDirectThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null;
    }

    public function rules(): array
    {
        return [
            // Existence/tenant membership checked in the service (fails 404),
            // so a foreign student id leaks nothing.
            'student_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'انتخاب دانش‌آموز الزامی است.',
        ];
    }
}
