{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود — {{ $tenant->name }}</title>
    <style>:root { --brand: {{ $config->primary_color ?? '#2563eb' }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-xl shadow-md w-full max-w-md p-8">

        <h1 class="text-2xl font-bold text-center mb-1">{{ $tenant->name }}</h1>
        <p class="text-center text-gray-500 mb-6">وارد حساب خود شوید</p>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded p-3 mb-4">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email" class="block mb-1 text-sm font-medium">ایمیل</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="w-full border rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500 text-left" dir="ltr">

            <label for="password" class="block mb-1 text-sm font-medium">رمز عبور</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full border rounded-lg px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500 text-left" dir="ltr">

            <label class="flex items-center gap-2 mb-6 text-sm text-gray-600">
                <input type="checkbox" name="remember" value="1" class="rounded"> مرا به خاطر بسپار
            </label>

            <button type="submit" class="w-full text-white font-semibold rounded-lg py-2.5" style="background: var(--brand)">
                ورود
            </button>
        </form>

        <a href="{{ route('home') }}" class="block text-center text-sm text-gray-500 mt-6 hover:underline">→ بازگشت به سایت</a>
    </div>

</body>
</html>
