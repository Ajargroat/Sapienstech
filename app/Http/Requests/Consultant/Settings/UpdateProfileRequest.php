<?php

namespace App\Http\Requests\Consultant\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Email uniqueness is a (tenant_id, email) constraint in the
            // schema, so the rule must mirror it — a global unique:users
            // would wrongly reject an address another tenant already uses.
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id))
                    ->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است.',
            'email.required' => 'ایمیل الزامی است.',
            'email.email' => 'ایمیل وارد شده معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً در این مجموعه ثبت شده است.',
        ];
    }
}
