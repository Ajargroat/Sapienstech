{{--
    Student report-card (کارنامه) page — its own topnav/sidebar tab, split out
    of the dashboard's old teaser panel. The real report-card pipeline is
    consultant-side for now, so this page still carries the "coming soon"
    notice; once populated, this is the view to expand (route:
    student.report-card → StudentReportCardController::index()).
--}}
@extends('layouts.student')

@section('content')
<div class="student-welcome">
    <section class="student-panel-head student-page-head">
        <h2><i class="fas fa-chart-pie"></i> کارنامه</h2>
    </section>
</div>

<div class="student-side">
    <section class="panel student-panel student-soon">
        <span class="student-soon-icon"><i class="fas fa-chart-pie"></i></span>
        <div>
            <h3>کارنامه</h3>
            <p>کارنامه تحصیلی شما به‌زودی در همین صفحه در دسترس خواهد بود.</p>
        </div>
    </section>
</div>
@endsection
