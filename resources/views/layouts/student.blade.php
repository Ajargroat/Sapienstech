<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل دانش‌آموز | {{ tenant()->name ?? 'آکادمی' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f3f4f6; margin: 0; padding: 0; color: #1f2937; }
        .nav { background: #fff; padding: 1rem 2rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .btn { background: #2563eb; color: #fff; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #ef4444; }
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
