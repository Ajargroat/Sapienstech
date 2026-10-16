<?php

namespace App\Http\Requests\Consultant\Settings;

use App\Rules\IranianPhone;
use App\Rules\NationalCode;
use App\Support\IranianIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Canonicalize the identity fields before validation, so the unique rules
     * compare the same stored form the model setters write — Persian digits
     * and +98 shapes would otherwise slip past them into the DB index.
     * Unparseable input is kept as typed so the format rules report it.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // Only touch keys the client actually sent: an absent field must
        // stay untouched (a partial PATCH), while an empty one clears it.
        if ($this->exists('phone')) {
            $phone = $this->input('phone');
            $merge['phone'] = IranianIdentity::normalizePhone($phone) ?? ($phone === '' ? null : $phone);
        }

        if ($this->exists('national_code')) {
            $code = $this->input('national_code');
            $merge['national_code'] = IranianIdentity::normalizeNationalCode($code) ?? ($code === '' ? null : $code);
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Email uniqueness is a (tenant_id, email) constraint in the
            // schema, so the rule must mirror it — a global unique:users
            // would wrongly reject an address another tenant already uses.
            // Optional: phone or کد ملی can carry the login instead.
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')
                    ->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id))
                    ->ignore($this->user()->id),
            ],
            // Phone is the (tenant_id, phone) login handle; کد ملی is unique
            // platform-wide (one person, one code across users + students).
            'phone' => [
                'nullable', 'string', 'max:20', new IranianPhone,
                Rule::unique('users', 'phone')
                    ->where(fn ($query) => $query->where('tenant_id', $this->user()->tenant_id))
                    ->ignore($this->user()->id),
            ],
            'national_code' => [
                'nullable', 'string', 'max:10',
                new NationalCode(available: true, ignoreUserId: $this->user()->id),
            ],
            // Shown under the name in the profile hub's identity header.
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است.',
            'email.email' => 'ایمیل وارد شده معتبر نیست.',
            'email.unique' => 'این ایمیل قبلاً در این مجموعه ثبت شده است.',
            'phone.unique' => 'این شمارهٔ موبایل قبلاً در این مجموعه ثبت شده است.',
        ];
    }
}
