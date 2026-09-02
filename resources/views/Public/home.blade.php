{{-- resources/views/public/home.blade.php --}}
@extends('layouts.public')

@section('content')
    <section class="text-center py-16">
        @if($config?->logo_path)
            <img src="{{ asset('storage/' . $config->logo_path) }}" alt="{{ $tenant->name }}" class="h-20 mx-auto mb-6">
        @endif

        <h1 class="text-4xl font-bold mb-4" style="color: var(--brand)">{{ $tenant->name }}</h1>

        @if($content->has('homepage'))
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">{{ $content['homepage']->body }}</p>
        @endif

        @guest
            <a href="{{ route('login') }}" class="inline-block mt-8 px-6 py-3 rounded-lg text-white font-semibold" style="background: var(--brand)">
                ورود مشاورین و دانش‌آموزان
            </a>
        @else
            <a href="{{ route('consultant.dashboard') }}" class="inline-block mt-8 px-6 py-3 rounded-lg text-white font-semibold" style="background: var(--brand)">
                رفتن به داشبورد
            </a>
        @endguest
    </section>

    {{-- Same code, different website per tenant — driven by the features table --}}
    <section class="grid md:grid-cols-3 gap-6 mt-12">
        @foreach(['blog' => 'وبلاگ', 'student_evaluation' => 'ارزیابی دانش‌آموز', 'chat' => 'گفتگو'] as $key => $label)
            @if($features->contains($key))
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="font-bold text-xl mb-2">{{ $label }}</h2>
                </div>
            @endif
        @endforeach
    </section>
@endsection
