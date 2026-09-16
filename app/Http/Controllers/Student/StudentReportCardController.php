<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The student's report-card (کارنامه) page, reachable from the portal's top
 * navigation. The real report-card data pipeline is consultant-side only for
 * now (see App\Http\Controllers\Consultant\StudentReportCardController), so
 * this page currently carries the "coming soon" notice that used to sit in a
 * dashboard panel.
 */
class StudentReportCardController extends Controller
{
    public function index(): View
    {
        return view('student.report-card');
    }
}
