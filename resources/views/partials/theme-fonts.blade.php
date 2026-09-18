@php
    $fontTypography = $fontTheme['typography'] ?? [];
    $fontTypography = is_array($fontTypography) ? $fontTypography : [];
    $localFaces = \App\Support\ThemeFonts::faces($fontTypography);
    $fontVars = \App\Support\ThemeFonts::vars($fontTypography);
@endphp
<style data-theme-fonts>
@foreach ($localFaces as $face)
    @font-face {
        font-family: {!! $face['family'] !!};
        src: url("{!! $face['src'] !!}") format("{!! $face['format'] !!}");
        font-weight: {!! $face['weight'] !!};
        font-style: {!! $face['style'] !!};
        font-display: {!! $face['display'] !!};
    }
@endforeach
    :root {
@foreach ($fontVars as $name => $value)
        --{{ $name }}: {!! $value !!};
@endforeach
    }
</style>
