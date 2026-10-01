@props(['path', 'bbox' => null, 'maxWidth' => 420])

{{-- The cropped region only. A question without a valid bbox renders nothing —
     a source page is not a picture of the question, so the whole page is never
     shown as one (see Question::has_croppable_image for the same predicate). --}}
@if ($path)
    @php
        $url = asset('storage/'.$path);
        $crop = is_array($bbox) && ($bbox['w'] ?? 0) > 0 && ($bbox['h'] ?? 0) > 0
            && ($bbox['pw'] ?? 0) > 0;
    @endphp

    @if ($crop)
        <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-200']) }}
             style="position:relative;max-width:{{ $maxWidth }}px;aspect-ratio:{{ round($bbox['w'] / $bbox['h'], 4) }}">
            <img src="{{ $url }}" alt="" loading="lazy"
                 style="position:absolute;max-width:none;width:{{ round($bbox['pw'] / $bbox['w'] * 100, 2) }}%;left:{{ round(-$bbox['x'] / $bbox['w'] * 100, 2) }}%;top:{{ round(-$bbox['y'] / $bbox['h'] * 100, 2) }}%">
        </div>
    @endif
@endif
