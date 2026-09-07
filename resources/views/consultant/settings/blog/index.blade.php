@extends('consultant.settings.layout')

@section('settings-content')
<div class="settings-cards">
    {{-- Landing source switch: writes public.landing.blog.source to the DB layer --}}
    <section class="settings-card">
        <h3 class="settings-card-title">منبع بخش وبلاگ در صفحه اصلی</h3>
        <p class="settings-card-text">
            مشخص کنید بخش «آخرین مقالات» در صفحه اصلی از نوشته‌های منتشرشدهٔ شما تغذیه شود یا محتوای ثابت پیکربندی.
        </p>
        <div class="blog-source-switch">
            <span class="blog-source-state {{ $landingFromDatabase ? 'is-on' : '' }}">
                <i class="fas fa-database" aria-hidden="true"></i>
                {{ $landingFromDatabase ? 'نوشته‌های واقعی (پایگاه داده)' : 'محتوای پیکربندی' }}
            </span>
            <form method="POST" action="{{ route('consultant.settings.blog.landing') }}" data-router="off">
                @csrf
                @method('PUT')
                <input type="hidden" name="source" value="{{ $landingFromDatabase ? 'config' : 'database' }}">
                <button type="submit" class="secondary-button">
                    {{ $landingFromDatabase ? 'بازگشت به پیکربندی' : 'استفاده از نوشته‌ها' }}
                </button>
            </form>
        </div>
    </section>

    {{-- Posts --}}
    <section class="settings-card">
        <div class="blog-list-head">
            <h3 class="settings-card-title" style="margin-bottom:0">نوشته‌ها</h3>
            <a href="{{ route('consultant.settings.blog.create') }}" class="primary-button">
                <i class="fas fa-plus" aria-hidden="true"></i> نوشتهٔ جدید
            </a>
        </div>

        <div class="blog-filters" data-router-region="results">
            <a href="{{ route('consultant.settings.blog.index') }}" class="blog-chip {{ request('status') === null ? 'is-active' : '' }}">همه</a>
            <a href="{{ route('consultant.settings.blog.index', ['status' => 'published']) }}" class="blog-chip {{ request('status') === 'published' ? 'is-active' : '' }}">منتشرشده</a>
            <a href="{{ route('consultant.settings.blog.index', ['status' => 'draft']) }}" class="blog-chip {{ request('status') === 'draft' ? 'is-active' : '' }}">پیش‌نویس</a>
        </div>

        <div data-router-region="results">
        @if($posts->count())
            <div class="blog-list">
                @foreach($posts as $post)
                    <div class="blog-row">
                        <div class="blog-row-cover">
                            @if($post->cover_image_path)
                                <img src="{{ tenant_asset($post->cover_image_path) }}" alt="">
                            @else
                                <i class="fa-solid fa-feather-pointed" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="blog-row-main">
                            <span class="blog-row-title">{{ $post->title }}</span>
                            <span class="blog-row-meta">
                                <span class="blog-status blog-status--{{ $post->status }}">
                                    {{ $post->isPublished() ? 'منتشرشده' : 'پیش‌نویس' }}
                                </span>
                                @if($post->published_at)
                                    <time datetime="{{ $post->published_at->toAtomString() }}">{{ persian_digits($post->published_at->format('Y/m/d')) }}</time>
                                @endif
                            </span>
                        </div>
                        <div class="blog-row-actions">
                            @if($post->isPublished())
                                <a href="{{ route('blog.show', $post->slug) }}" target="_blank" rel="noopener" class="icon-action" title="مشاهده"><i class="fas fa-external-link-alt"></i></a>
                            @endif
                            <a href="{{ route('consultant.settings.blog.edit', $post) }}" class="icon-action" title="ویرایش"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('consultant.settings.blog.destroy', $post) }}" data-router="off"
                                  onsubmit="return confirm('این نوشته حذف شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action icon-action--danger" title="حذف"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($posts->hasPages())
                <div class="blog-pager">{{ $posts->links() }}</div>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-newspaper"></i>
                <h3>هنوز نوشته‌ای ثبت نشده</h3>
                <p>با «نوشتهٔ جدید» اولین مقالهٔ وبلاگ را بسازید.</p>
            </div>
        @endif
        </div>
    </section>
</div>
@endsection
