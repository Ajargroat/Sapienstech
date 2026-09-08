@extends('public.blog.layout')

@section('blog-content')
<section class="lp-section">
    <div class="landing-container">
        @include('public.sections._heading', [
            'heading'    => $blogCfg['heading'] ?? 'آخرین مقالات',
            'subheading' => $blogCfg['subheading'] ?? null,
            'align'      => 'center',
        ])

        @if($posts->count())
            <div class="grid grid-cols-1 md:grid-cols-[repeat(var(--cols),minmax(0,1fr))] gap-[var(--grid-gap)]"
                 style="--cols:{{ $blogCfg['columns'] ?? 3 }}">
                @foreach($posts as $post)
                    <article class="lp-card group cursor-pointer p-5 reveal">
                        <a href="{{ route('blog.show', $post->slug) }}" class="block">
                            <div class="w-full h-48 rounded-2xl mb-6 overflow-hidden relative"
                                 style="background:var(--c-surface-alt);border:1px solid var(--c-border)">
                                <div class="absolute inset-0 group-hover:scale-105 transition-transform duration-500"
                                     style="background:linear-gradient(135deg, color-mix(in srgb, var(--c-primary) 10%, transparent), color-mix(in srgb, var(--c-secondary) 10%, transparent))"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    @if($post->cover_image_path)
                                        <img src="{{ tenant_asset($post->cover_image_path) }}" alt="{{ $post->title }}" class="max-w-full max-h-full object-cover" loading="lazy">
                                    @else
                                        <i class="fa-solid fa-feather-pointed" style="font-size:2rem;color:var(--c-subtle)" aria-hidden="true"></i>
                                    @endif
                                </div>
                            </div>
                            <h3 class="text-xl font-bold mb-3">{{ $post->title }}</h3>
                            <p class="text-sm mb-4 line-clamp-2" style="color:var(--c-muted)">
                                {{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 120) }}
                            </p>
                            @if($post->published_at)
                                <time class="text-xs" style="color:var(--c-subtle)" datetime="{{ $post->published_at->toAtomString() }}">
                                    {{ persian_digits($post->published_at->format('Y/m/d')) }}
                                </time>
                            @endif
                        </a>
                    </article>
                @endforeach
            </div>

            <div class="blog-pager" style="margin-top:2.5rem">{{ $posts->links() }}</div>
        @else
            <p class="blog-empty" style="text-align:center;color:var(--c-muted)">هنوز مقاله‌ای منتشر نشده است.</p>
        @endif
    </div>
</section>
@endsection
