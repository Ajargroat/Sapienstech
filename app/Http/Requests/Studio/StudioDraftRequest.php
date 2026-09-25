<?php

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Create / update for THE single studio draft (one per tenant+user).
 *
 * `payload` is the browser's form snapshot — field name => list of submitted
 * values. It is deliberately validated as structure only (shape + size): the
 * content never reaches a config layer here. A restore re-submits it through
 * the ordinary save pipeline (StudioSaveRequest, scope=preview), where the
 * config/studio.php whitelist re-validates every value — same guard as a
 * hand-typed form. Draft is NOT publish.
 */
class StudioDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    /**
     * `_token` / `scope` / `_method` are the three names that could turn a
     * replayed snapshot into an unprompted publish or a forged CSRF — drop
     * them on the way in so the stored payload cannot carry them at all.
     */
    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');

        if (is_array($payload)) {
            $this->merge(['payload' => Arr::except($payload, ['_token', '_method', 'scope'])]);
        }
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'payload' => [$creating ? 'required' : 'sometimes', 'array', 'max:2000'],
            'payload.*' => ['array', 'max:100'],
            // nullable because the global ConvertEmptyStringsToNull middleware
            // runs first: an empty or whitespace-only form value arrives as
            // null, and an absent value is its exact semantic twin anyway.
            'payload.*.*' => ['nullable', 'string', 'max:500000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A rename-only or payload-only update is fine; an empty one is not.
            if ($this->isMethod('PUT') && ! $this->filled('name') && ! $this->has('payload')) {
                $validator->errors()->add('name', 'Provide a draft name or payload.');
            }

            $payload = $this->input('payload');

            if (is_array($payload) && strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) > 1_000_000) {
                $validator->errors()->add('payload', 'The draft is larger than the allowed limit.');
            }
        });
    }
}
