{{-- resources/views/layouts/public.blade.php --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $tenant->name)</title>
    <style>:root { --brand: {{ $config->primary_color ?? '#2563eb' }}; }</style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen flex flex-col">

    <nav class="bg-white shadow">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-xl" style="color: var(--brand)">
                {{ $tenant->name }}
            </a>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('home') }}" class="hover:underline">خانه</a>
                <a href="{{ route('about') }}" class="hover:underline">درباره ما</a>
                <a href="{{ route('contact') }}" class="hover:underline">تماس</a>

                @guest
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded text-white" style="background: var(--brand)">ورود</a>
                @else
                    <a href="{{ route('consultant.dashboard') }}" class="px-4 py-2 rounded text-white" style="background: var(--brand)">داشبورد</a>
                @endguest
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-5xl w-full mx-auto px-4 py-10">
        @yield('content')
    </main>

</body>
</html>
