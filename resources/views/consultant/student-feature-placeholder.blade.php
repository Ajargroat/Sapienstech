@extends('layouts.consultant')

@section('content')
    <div class="dashboard-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>شناسه دانش‌آموز: {{ persian_digits($student) }}</p>
        </div>
    </div>

    <section class="panel feature-placeholder">
        <span class="feature-placeholder-icon">
            <i class="fas fa-user-graduate"></i>
        </span>
        <h2>Student feature boundary</h2>
        <p>در پیاده‌سازی واقعی، دانش‌آموز باید فقط از tenant جاری پیدا شود و هرگز به ID به تنهایی اعتماد نشود.</p>
    </section>
@endsection