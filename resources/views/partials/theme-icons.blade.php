@php($iconSet = \App\Support\ThemeIcons::selected(site('theme.icons.set')))
{{-- Font Awesome remains the fallback for brands and concepts absent from a set. --}}
<link rel="stylesheet" href="{{ site('theme.assets.icon_library_url', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css') }}">
@if ($iconSet !== 'font-awesome')
    <link rel="stylesheet" href="{{ asset('icons/'.$iconSet.'.css') }}" data-icon-set="{{ $iconSet }}">
@endif
