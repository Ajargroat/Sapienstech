<!DOCTYPE html>
{{--
    Platform shell for the standalone Theme Studio (/studio).

    Isolation contract: this layout deliberately includes NO tenant theme
    partials — no partials/theme-vars, no theme-attrs, no color-scheme, no
    consultant nav, no preview-banner — and reads nothing from site(). Every
    custom property the studio chrome consumes is pinned to the platform
    baseline (config/theme.php defaults) by resources/css/features/
    studio-shell.css under the `studio-shell` body class, so editing a
    tenant's theme can never restyle the editor itself.

    Same host on purpose: IdentifyTenant resolves the tenant from the Host
    header, so session cookies, uploads and the same-origin preview iframe
    keep working unchanged. (A future central studio domain would need a
    token-auth + postMessage bridge and is explicitly out of scope.)
--}}
<html lang="{{ config('theme.tenant.locale', 'fa') }}" dir="{{ config('theme.tenant.direction', 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Platform name only: the shell must not inherit the tenant brand. --}}
    <title>استودیوی ظاهر | {{ config('theme.tenant.name', 'Sapienstech') }}</title>

    {{-- Platform-baseline fonts from config/theme.php defaults (never site()).
         The studio chrome keeps a fixed type ramp no matter what the tenant
         preview shows. --}}
    @include('partials.theme-fonts', ['fontTheme' => config('theme.theme', [])])

    @vite(['resources/css/app.css', 'resources/js/features/studio-standalone.js'])

    {{-- Icon library for the studio chrome. Hardcoded to the platform
         default on purpose: a tenant's icon-set lever restyles only the
         previewed site, never the editor (theme-icons partial reads site(),
         so it must not be included here). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="studio-shell">
    <div class="studio-page">
        @yield('content')
    </div>
</body>
</html>
