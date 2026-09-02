{{-- resources/views/public/about.blade.php --}}
@extends('layouts.public')

@section('content')
    <h1 class="text-3xl font-bold mb-6" style="color: var(--brand)">درباره ما</h1>
    <div class="bg-white rounded-lg shadow p-6">
        @if($content)
            <h2 class="text-xl font-semibold mb-3">{{ $content->title }}</h2>
            <div class="text-gray-700 leading-7">{!! nl2br(e($content->body)) !!}</div>
        @else
            <p class="text-gray-500">هنوز محتوایی برای این بخش تنظیم نشده است.</p>
        @endif
    </div>
@endsection
