<?php

namespace App\Http\Controllers\Consultant;

use App\Http\Controllers\Controller;

class ConsultantFeatureController extends Controller
{
    public function show(string $feature)
    {
        $pages = [
            // direct-chat moved to the real ChatController workspace.

            // Legacy top-level placeholders kept for backward compatibility.
            // These are no longer linked from the top navigation (the
            // sidebar they used to live in has been removed), but the
            // routes/pages themselves are left intact rather than deleted.
            'permissions' => [
                'title' => 'دسترسی کتاب',
                'description' => 'مدیریت دسترسی دانش‌آموزان به منابع آموزشی.',
            ],
            'questions' => [
                'title' => 'مدیریت سوالات',
                'description' => 'مدیریت بانک سوالات.',
            ],
            'quizzes' => [
                'title' => 'مدیریت آزمون‌ها',
                'description' => 'ساخت و مدیریت آزمون‌ها.',
            ],
        ];

        abort_unless(isset($pages[$feature]), 404);

        return view('consultant.feature-placeholder', [
            'page' => $pages[$feature],
            'feature' => $feature,
        ]);
    }
}
