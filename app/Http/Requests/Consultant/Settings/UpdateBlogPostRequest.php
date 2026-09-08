<?php

namespace App\Http\Requests\Consultant\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        return array_merge(StoreBlogPostRequest::sharedRules(), [
            'remove_cover' => ['nullable', 'boolean'],
        ]);
    }

    public function messages(): array
    {
        return StoreBlogPostRequest::sharedMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', \App\Models\BlogPost::STATUS_DRAFT),
        ]);
    }
}
