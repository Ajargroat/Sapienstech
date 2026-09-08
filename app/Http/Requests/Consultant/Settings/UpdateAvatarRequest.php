<?php

namespace App\Http\Requests\Consultant\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.image' => 'فایل انتخاب شده باید تصویر باشد.',
            'avatar.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }
}
