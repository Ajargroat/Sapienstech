<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل دانش‌آموز | {{ tenant()->name ?? 'آکادمی' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    @include('partials.theme-vars')
    <style>
        body { font-family: var(--font-body); background: var(--c-background); margin: 0; padding: 0; color: var(--c-text); line-height: var(--line-height); }
        .nav { background: var(--c-surface); padding: 1rem 2rem; border-bottom: 1px solid var(--c-border); display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: var(--container-max); margin: 2rem auto; padding: 0 1rem; }
        .card { background: var(--c-surface); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--c-border); box-shadow: var(--card-shadow); }
        .btn { background: var(--c-primary); color: var(--c-on-primary); padding: 0.5rem 1rem; border: none; border-radius: var(--radius-button); cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: var(--c-danger); color: var(--c-on-surface); }
    </style>
</head>
<body>
    <nav class="nav">
        <strong>پنل دانش‌آموز</strong>
        @auth('student')
            <div>
                <span style="margin-left: 1rem;">سلام، {{ auth('student')->user()->name }}</span>
                <form action="{{ route('student.logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-danger">خروج</button>
                </form>
            </div>
        @endauth
    </nav>

    <div class="container">
        @yield('content')
    </div>
</body>
</html>
