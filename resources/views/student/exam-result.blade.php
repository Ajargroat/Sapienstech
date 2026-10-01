{{--
    Student exam result review — percent, verdict chips and the question-by-
    question بررسی.

    Rendered by App\Http\Controllers\Student\StudentExamController::result()
    through the `student.exams.result` route. The hero and the review markup are
    shared partials, so the student and consultant pages can never drift apart;
    see App\Support\ExamReport for the data contract they both read.

    The completion date is Jalali-rendered by
    resources/js/features/student-dashboard.js (fa-date rewrite).
--}}
@extends('layouts.student')

@section('content')
<p class="result-back">
    <a href="{{ route('student.dashboard') }}"><i class="fas fa-arrow-right"></i> بازگشت به داشبورد</a>
</p>
<div class="result-wrap">
    @include('partials.exam-result-hero', ['report' => $report, 'test' => $test])
    @include('partials.exam-result-review', ['questions' => $questions, 'answers' => $answers])
</div>

@vite(['resources/js/features/student-dashboard.js'])
@endsection
