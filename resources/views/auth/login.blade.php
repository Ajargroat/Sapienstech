{{-- resources/views/auth/login.blade.php --}}
{{-- Config-driven login: same tenant/theme tokens as the landing page and
     the consultant dashboard (see config/consultant.php → public.login). --}}
@php($login = $public['login'])
<!DOCTYPE html>
<html lang="{{ $tenant['locale'] }}" dir="{{ $tenant['direction'] }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $login['title'] }} — {{ $tenant['name'] }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ $theme['assets']['font_url'] }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.theme-vars')
</head>
<body class="landing flex items-center justify-center min-h-screen p-4">
    <div class="page-glow page-glow-primary"></div>
    <div class="page-glow page-glow-secondary"></div>

    <div class="w-full p-8 relative z-10 rounded-[var(--radius-card)]"
         style="max-width:{{ $login['card']['max_width'] }};
                background:color-mix(in srgb, var(--c-surface) calc({{ $theme['effects']['glass_opacity'] }} * 100%), transparent);
                border:1px solid var(--c-border);
                box-shadow:var(--card-shadow);
                {{ $login['card']['glass'] ? 'backdrop-filter:blur(var(--backdrop-blur));' : '' }}">

        <h1 class="text-2xl font-bold text-center mb-1">{{ $tenant['name'] }}</h1>
        <p class="text-center mb-6" style="color:var(--c-muted)">{{ $login['subtitle'] }}</p>

        @if ($errors->any())
            <div class="text-sm rounded-[var(--radius-input)] p-3 mb-4"
                 style="background:color-mix(in srgb, var(--c-danger) 10%, transparent);border:1px solid var(--c-danger);color:var(--c-danger)">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email" class="block mb-1 text-sm font-medium">{{ $login['email_label'] }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)" dir="ltr"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label for="password" class="block mb-1 text-sm font-medium">{{ $login['password_label'] }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="w-full px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-(--c-primary)" dir="ltr"
                   style="background:var(--c-surface-alt);border:1px solid var(--c-border);border-radius:var(--radius-input);color:var(--c-text)">

            <label class="flex items-center gap-2 mb-6 text-sm" style="color:var(--c-muted)">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ $login['remember_label'] }}
            </label>

            <button type="submit" class="w-full font-semibold rounded-[var(--radius-button)] py-2.5 transition-colors hover:opacity-90"
                    style="background:var(--c-primary);color:var(--c-on-primary)">
                {{ $login['submit_label'] }}
            </button>
        </form>

        @if ($login['student_link']['visible'] ?? true)
            <a href="{{ route($login['student_link']['route']) }}"
               class="block w-full text-center font-semibold rounded-[var(--radius-button)] py-2.5 mt-4 transition-colors hover:opacity-90"
               style="background:var(--c-surface-alt);border:1px solid var(--c-border);color:var(--c-text)">
                {{ $login['student_link']['label'] }}
            </a>
        @endif

        @if ($login['back_link']['visible'] ?? true)
            <a href="{{ route($login['back_link']['route']) }}" class="block text-center text-sm mt-6 hover:underline"
               style="color:var(--c-muted)">→ {{ $login['back_link']['label'] }}</a>
        @endif
    </div>
</body>
</html>
