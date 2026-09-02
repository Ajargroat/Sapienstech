{{-- resources/views/public/contact.blade.php --}}
@extends('layouts.public')

@section('content')
    <h1 class="text-3xl font-bold mb-6" style="color: var(--brand)">تماس با ما</h1>
    <div class="bg-white rounded-lg shadow p-6">
        @if($content)
            <div class="text-gray-700 leading-7">{!! nl2br(e($content->body)) !!}</div>
        @else
            <p class="text-gray-500">هنوز اطلاعات تماسی تنظیم نشده است.</p>
        @endif
    </div>
@endsection
