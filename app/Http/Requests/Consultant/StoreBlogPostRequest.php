<?php

namespace App\Http\Requests\Consultant;

use App\Models\BlogPost;
use App\Support\RichText;
use Illuminate\Foundation\Http\FormRequest;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && tenant() !== null;
    }

    public function rules(): array
    {
        return self::sharedRules();
    }

    /** Shared with UpdateBlogPostRequest so create/edit never drift apart. */
    public static function sharedRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', 'max:100000'],
            'status' => ['required', 'in:'.BlogPost::STATUS_DRAFT.','.BlogPost::STATUS_PUBLISHED],
            // Cover is optional: posts render fine without a picture.
            // Order is not a form field — the list drag-handles own it.
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ];
    }

    public static function sharedMessages(): array
    {
        return [
            'title.required' => 'عنوان نوشته الزامی است.',
            'status.required' => 'وضعیت انتشار را انتخاب کنید.',
            'cover.image' => 'فایل انتخاب شده باید تصویر باشد.',
            'cover.max' => 'حجم تصویر جلد نباید بیشتر از ۴ مگابایت باشد.',
        ];
    }

    /**
     * The body arrives as editor HTML. It is normalized and sanitized once
     * here so validation, storage and rendering all see the same value.
     * Shared with UpdateBlogPostRequest; absent bodies (partial PATCHes)
     * are left untouched.
     */
    public static function normalizeBody(FormRequest $request): void
    {
        if ($request->has('body')) {
            $request->merge(['body' => RichText::sanitize($request->input('body'))]);
        }
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status', BlogPost::STATUS_DRAFT),
        ]);

        self::normalizeBody($this);
    }
}
