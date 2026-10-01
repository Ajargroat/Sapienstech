{{--
    Consultant review of a finished attempt: the same hero and بررسی the
    student sees, from the same data contract, with a link back to the exam
    workspace instead of the student dashboard.

    Rendered by App\Http\Controllers\Consultant\StudentExamController::result().
--}}
@extends('layouts.consultant')

@section('content')
<p class="result-back">
    <a href="{{ route('consultant.student.exams', $student) }}"><i class="fas fa-arrow-right"></i> بازگشت به فهرست آزمون‌ها</a>
</p>
<div class="result-wrap">
    @include('partials.exam-result-hero', ['report' => $report, 'test' => $test])
    @include('partials.exam-result-review', ['questions' => $questions, 'answers' => $answers])
</div>
@endsection
