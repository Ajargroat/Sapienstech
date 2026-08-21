<?php namespace App\Http\Controllers\Consultant; use App\Http\Controllers\Controller;
class ConsultantFeatureController extends Controller{
 public function show(string $feature){$pages=['blog'=>['title'=>'مدیریت وبلاگ','description'=>'مدیریت نوشته‌ها و محتوای آموزشی.'],'permissions'=>['title'=>'دسترسی کتاب','description'=>'مدیریت دسترسی دانش‌آموزان به منابع آموزشی.'],'questions'=>['title'=>'مدیریت سوالات','description'=>'مدیریت بانک سوالات.'],'quizzes'=>['title'=>'مدیریت آزمون‌ها','description'=>'ساخت و مدیریت آزمون‌ها.']];abort_unless(isset($pages[$feature]),404);return view('consultant.feature-placeholder',['page'=>$pages[$feature],'feature'=>$feature]);}
}
