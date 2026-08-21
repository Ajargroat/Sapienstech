<?php namespace App\Http\Controllers\Consultant; use App\Http\Controllers\Controller;
class StudentFeatureController extends Controller{
 public function show(string $feature,int $student){$labels=['schedule'=>'برنامه دانش‌آموز','quizzes'=>'آزمون‌های دانش‌آموز','report-card'=>'کارنامه دانش‌آموز'];abort_unless(isset($labels[$feature]),404);return view('consultant.student-feature-placeholder',['title'=>$labels[$feature],'student'=>$student]);}
}
