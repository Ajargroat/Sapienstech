@extends('layouts.consultant')

@php
    $isEdit = $post->exists;
@endphp

@section('content')
<div class="panel-heading">
    <div class="panel-heading-title">
        <h2>{{ $isEdit ? 'ویرایش نوشته' : 'نوشتهٔ جدید' }}</h2>
    </div>

    <div class="panel-heading-actions">
        <a href="{{ route('consultant.blog.index') }}" class="secondary-button">
            <i class="fas fa-arrow-right" aria-hidden="true"></i> بازگشت
        </a>
    </div>
</div>

@include('consultant.blog.flash')
@include('consultant.blog.form')

@vite(['resources/js/features/blog-editor.js'])
@endsection
