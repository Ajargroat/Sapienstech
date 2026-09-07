@extends('public.blog.layout')

@section('blog-title', $post->title.' | '.$tenant['name'])

@section('blog-content')
<article class="lp-section">
    <div class="landing-container" style="max-width:var(--measure);margin-inline:auto">
        <header class="blog-post-head">
            <h1 class="blog-post-title">{{ $post->title }}</h1>
            @if($post->published_at)
                <time class="blog-post-date" datetime="{{ $post->published_at->toAtomString() }}">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    {{ persian_digits($post->published_at->format('Y/m/d')) }}
                </time>
            @endif
        </header>

        @if($post->cover_image_path)
            <div class="blog-post-cover">
                <img src="{{ tenant_asset($post->cover_image_path) }}" alt="{{ $post->title }}">
            </div>
        @endif

        @if($post->excerpt)
            <p class="blog-post-excerpt">{{ $post->excerpt }}</p>
        @endif

        <div class="blog-post-body">
            {!! nl2br(e($post->body)) !!}
        </div>

        <a href="{{ route('blog.index') }}" class="secondary-button" style="margin-top:2rem">
            <i class="fas fa-list" aria-hidden="true"></i> همه مقالات
        </a>
    </div>
</article>
@endsection
