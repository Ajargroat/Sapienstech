<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Consultant\ConsultantDashboardController;
use App\Http\Controllers\Consultant\ConsultantFeatureController;
use App\Http\Controllers\Consultant\StudentFeatureController;
Route::redirect('/','/consultant/dashboard');
Route::prefix('consultant')->name('consultant.')->group(function(){
 Route::get('/dashboard',[ConsultantDashboardController::class,'index'])->middleware('consultant.feature:dashboard')->name('dashboard');
 Route::get('/blog',[ConsultantFeatureController::class,'show'])->defaults('feature','blog')->middleware('consultant.feature:blog_management')->name('blog');
 Route::get('/permissions',[ConsultantFeatureController::class,'show'])->defaults('feature','permissions')->middleware('consultant.feature:book_access')->name('permissions');
 Route::get('/questions',[ConsultantFeatureController::class,'show'])->defaults('feature','questions')->middleware('consultant.feature:question_management')->name('questions');
 Route::get('/quizzes',[ConsultantFeatureController::class,'show'])->defaults('feature','quizzes')->middleware('consultant.feature:quiz_management')->name('quizzes');
 Route::get('/students/{student}/schedule',[StudentFeatureController::class,'show'])->defaults('feature','schedule')->middleware('consultant.feature:student_schedule')->name('student.schedule');
 Route::get('/students/{student}/quizzes',[StudentFeatureController::class,'show'])->defaults('feature','quizzes')->middleware('consultant.feature:student_quizzes')->name('student.quizzes');
 Route::get('/students/{student}/report-card',[StudentFeatureController::class,'show'])->defaults('feature','report-card')->middleware('consultant.feature:report_cards')->name('student.report-card');
});
