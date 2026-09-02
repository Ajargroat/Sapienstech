<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود دانش‌آموز</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input { width: 100%; padding: 0.5rem; margin-bottom: 1rem; border: 1px solid #d1d5db; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .error { color: #ef4444; font-size: 0.875rem; margin-top: -0.5rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>ورود به پنل دانش‌آموز</h2>
        <form method="POST" action="{{ route('student.login') }}">
            @csrf
            <div>
                <label for="email">ایمیل</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="password">رمز عبور</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit">ورود</button>
        </form>
    </div>
</body>
</html>
