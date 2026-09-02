@extends('layouts.student')

@section('content')
    <div class="card">
        <h1>خوش آمدید، {{ $student->name }}</h1>
        <p>این پنل دانش‌آموزی شماست. در اینجا می‌توانید برنامه هفتگی، کارنامه و آزمون‌های خود را مشاهده کنید.</p>

        <h3>اطلاعات پایه</h3>
        <ul>
            <li><strong>پایه تحصیلی:</strong> {{ $student->grade ?? 'ثبت نشده' }}</li>
            <li><strong>رشته:</strong> {{ $student->major ?? 'ثبت نشده' }}</li>
            <li><strong>ایمیل:</strong> {{ $student->email }}</li>
        </ul>
    </div>
@endsection
