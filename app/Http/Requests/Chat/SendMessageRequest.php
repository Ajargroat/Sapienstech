<?php

namespace App\Http\Requests\Chat;

use App\Support\ChatService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Sending a chat message. Hard limits (length, rate, closed-thread) live in
 * ChatService::sendMessage — this request only shapes the transport: body
 * text and an optional single attachment, validated against the tenant's
 * chat config so UI hints and enforcement read from the same knobs.
 */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return tenant() !== null;
    }

    public function rules(): array
    {
        $maxLength = max(100, (int) ChatService::config('message_max_length', 4000));
        $maxKb = max(1, (int) ChatService::config('attachments.max_kb', 5120));

        return [
            'body' => ['nullable', 'string', "max:{$maxLength}"],
            'attachment' => [
                ChatService::config('attachments.enabled', true) ? 'nullable' : 'blank',
                'file',
                "max:{$maxKb}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.max' => 'طول پیام بیش از حد مجاز است.',
            'attachment.file' => 'آپلود فایل ناموفق بود؛ حجم فایل یا تنظیمات سرور را بررسی کنید.',
            'attachment.max' => 'حجم فایل بیش از حد مجاز است.',
        ];
    }

    public function attachment(): ?UploadedFile
    {
        $file = $this->file('attachment');

        return $file instanceof UploadedFile ? $file : null;
    }
}
