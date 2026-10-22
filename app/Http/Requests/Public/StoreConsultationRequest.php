<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a public consultation request.
 *
 * Every field is a scalar; a malformed value (an array where a string is
 * expected, a nested payload, …) is a validation ERROR, never an exception —
 * the `string` rule fails cleanly and the framework answers with a redirect
 * back with errors, or a 422 for a JSON request.
 */
class StoreConsultationRequest extends FormRequest
{
    /** Hard cap on the free-text message. */
    public const MESSAGE_MAX = 4000;

    public function authorize(): bool
    {
        // The controller owns the gate: tenant from host + feature flag.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Email is the pending-dedupe key for this table, so it is
            // required — a visitor without one cannot be deduped.
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:'.self::MESSAGE_MAX],
        ];
    }

    public function messages(): array
    {
        // A rejection carries the vocabulary that would have satisfied it:
        // here, the cap itself.
        return [
            'message.max' => 'متن درخواست نمی‌تواند بیش از :max نویسه باشد.',
        ];
    }
}
