{{--
    The operations console's own chrome.

    Deliberately NOT a tenant layout: the pipeline is internal machinery, so it
    must not inherit a tenant's nav, feature flags or colour tokens. Colours
    live in resources/css/features/ops.css (editor chrome never consumes tenant
    tokens — PREFERENCE §2).
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'کنسول عملیات')</title>
    @vite(['resources/css/features/ops.css'])
</head>
<body class="ops">

<header class="ops-top">
    <a class="ops-brand" href="{{ route('ops.lessons.index') }}">
        <span class="ops-brand-mark" aria-hidden="true">S</span>
        <span>کنسول عملیات</span>
    </a>

    <nav class="ops-nav" aria-label="بخش‌ها">
        <a href="{{ route('ops.lessons.index') }}"
           @class(['ops-nav-link', 'is-on' => request()->routeIs('ops.lessons.*')])>کتاب‌ها و استخراج</a>
    </nav>

    <div class="ops-spacer"></div>

    <span class="ops-who">{{ auth()->user()?->name }}</span>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="ops-ghost" type="submit">خروج</button>
    </form>
</header>

<main class="ops-main">
    @if(session('success'))
        <p class="ops-flash">{{ session('success') }}</p>
    @endif

    @if($errors->any())
        <p class="ops-flash ops-flash--bad">{{ $errors->first() }}</p>
    @endif

    @yield('content')
</main>

</body>
</html>
