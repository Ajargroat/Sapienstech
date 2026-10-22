<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\ReportCard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's report card (کارنامه) — their own exams, side by side.
 *
 * Replaces the "coming soon" panel with the real contract
 * (App\Support\ReportCard), which merges the tenant's in-system exams with the
 * corporation mock results the consultant imported for this student.
 *
 * The page never asks who the student is: it reads the authenticated
 * `student` guard, and both models carry BelongsToTenant, so a student can only
 * ever see their own tenant's rows.
 */
class StudentReportCardController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user('student');
        $report = ReportCard::forStudent($student);

        return view('student.report-card', [
            'student' => $student,
            'report' => $report,
        ]);
    }
}
