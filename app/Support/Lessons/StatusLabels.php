<?php

namespace App\Support\Lessons;

/**
 * Persian copy for the pipeline's closed sets. Keeping the map here (not in
 * each view) means a new state cannot be added without deciding what staff
 * read for it — and the tests can assert the map is exhaustive.
 */
class StatusLabels
{
    /** @return array<string, string> */
    public static function import(): array
    {
        return [
            'queued' => 'در صف',
            'running' => 'در حال استخراج',
            'ready' => 'آماده',
            'needs_ocr' => 'نیازمند OCR',
            'failed' => 'ناموفق',
        ];
    }

    /** @return array<string, string> */
    public static function structure(): array
    {
        return [
            'wrapped' => 'متن قابل استخراج',
            'scanned' => 'اسکن‌شده (بدون لایهٔ متن)',
            'unknown' => 'نامشخص',
        ];
    }

    /** @return array<string, string> */
    public static function lesson(): array
    {
        return [
            'importing' => 'در حال ساخت',
            'draft' => 'پیش‌نویس',
            'published' => 'منتشرشده',
        ];
    }

    /** @return array<string, string> */
    public static function draft(): array
    {
        return [
            'queued' => 'در صف',
            'running' => 'در حال تولید',
            'ready' => 'آمادهٔ بررسی',
            'failed' => 'ناموفق',
            'refused' => 'ردشده توسط اعتبارسنج',
            'approved' => 'تأییدشده',
            'rejected' => 'ردشده',
            'released' => 'وارد بانک سؤال شد',
        ];
    }

    /** @return array<string, string> */
    public static function blockTypes(): array
    {
        return [
            'heading' => 'تیتر',
            'paragraph' => 'پاراگراف',
            'points' => 'نکته‌های فهرستی',
            'equation' => 'معادله',
            'tip' => 'کادر نکته',
            'figure' => 'تصویر',
            'figure_duo' => 'دو تصویر کنار هم',
        ];
    }
}
