<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A student's comment on one of their schedule blocks -- the "I could not do
 * it because I was ill" note the consultant reads back on the same thread.
 *
 * There is no author field: the student is always the authenticated user, and
 * the controller stamps commenter_student_id from that guard. Accepting an
 * author from the client would be an IDOR.
 */
class StoreScheduleItemCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('student') !== null;
    }

    public function rules(): array
    {
        return [
            'comment_text' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment_text.required' => 'متن نظر را وارد کنید.',
            'comment_text.max' => 'متن نظر حداکثر ۲۰۰۰ نویسه می‌تواند باشد.',
        ];
    }
}
