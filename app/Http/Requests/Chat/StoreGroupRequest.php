<?php

namespace App\Http\Requests\Chat;

use App\Support\ChatService;
use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null;
    }

    public function rules(): array
    {
        $max = max(2, (int) ChatService::config('groups.max_members', 50));

        return [
            'title' => ['required', 'string', 'max:120'],
            'student_ids' => ['required', 'array', 'min:1', "max:{$max}"],
            'student_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'نام گروه الزامی است.',
            'student_ids.required' => 'حداقل یک دانش‌آموز برای ساخت گروه انتخاب کنید.',
        ];
    }
}
