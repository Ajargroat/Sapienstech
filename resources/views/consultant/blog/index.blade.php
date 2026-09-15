@extends('layouts.consultant')

@section('content')
<div class="panel-heading">
    <div class="panel-heading-title">
        <h2>{{ $labels['blog_management'] ?? 'وبلاگ' }}</h2>
        <span class="count-badge" data-router-region="results">{{ persian_digits($posts->total()) }} نوشته</span>
    </div>

    <div class="panel-heading-actions">
        <a href="{{ route('consultant.blog.create') }}" class="exam-tool"
           aria-label="نوشتهٔ جدید" title="نوشتهٔ جدید"
           data-router="off" data-blog-form data-form-title="نوشتهٔ جدید">
            <i class="fas fa-plus" aria-hidden="true"></i>
        </a>
    </div>
</div>

@include('consultant.blog.flash')

<div class="blog-filters" data-router-region="results">
    <a href="{{ route('consultant.blog.index') }}" class="blog-chip {{ request('status') === null ? 'is-active' : '' }}">همه</a>
    <a href="{{ route('consultant.blog.index', ['status' => 'published']) }}" class="blog-chip {{ request('status') === 'published' ? 'is-active' : '' }}">منتشرشده</a>
    <a href="{{ route('consultant.blog.index', ['status' => 'draft']) }}" class="blog-chip {{ request('status') === 'draft' ? 'is-active' : '' }}">پیش‌نویس</a>
</div>

<div data-router-region="results">
@if($posts->count())
    @if($posts->count() > 1)
        <p class="blog-sort-hint">
            <i class="fas fa-grip-vertical" aria-hidden="true"></i>
            برای تغییر ترتیب نمایش، نوشته‌ها را از دستگیرهٔ کنارشان بکشید.
        </p>
    @endif
    <div class="blog-list"
         data-blog-sort
         data-sort-url="{{ route('consultant.blog.reorder') }}"
         data-csrf="{{ csrf_token() }}">
        @foreach($posts as $post)
            <div class="blog-row" data-post-id="{{ $post->id }}">
                <button type="button" class="blog-row-grip" aria-label="جابه‌جایی «{{ $post->title }}»"
                        title="برای تغییر ترتیب، بکشید یا با کلیدهای ↑/↓ جابه‌جا کنید">
                    <i class="fas fa-grip-vertical" aria-hidden="true"></i>
                </button>
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
                    <a href="{{ route('consultant.blog.edit', $post) }}" class="icon-action" title="ویرایش"
                       data-router="off" data-blog-form data-form-title="ویرایش نوشته"><i class="fas fa-pen"></i></a>
                    <form method="POST" action="{{ route('consultant.blog.destroy', $post) }}" data-router="off"
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
        <p>با دکمهٔ «+» بالای صفحه اولین مقالهٔ وبلاگ را بسازید.</p>
    </div>
@endif
</div>

{{-- Editor dialog: consultant-blog-modal.js fetches the form fragment from
     the create/edit routes (Accept: application/json) and injects it into
     the slot. Headless users still get the full page via the links' hrefs. --}}
<dialog id="blog-form-modal" class="exam-modal blog-form-modal"
        aria-label="فرم نوشته"
        data-index-url="{{ route('consultant.blog.index') }}">
    <div class="blog-form-modal-head">
        <h3>
            <i class="fas fa-feather-pointed" aria-hidden="true"></i>
            <span data-blog-modal-title>نوشتهٔ جدید</span>
        </h3>
        <button type="button" class="blog-form-modal-x" data-blog-form-close aria-label="بستن">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="blog-form-modal-body" data-blog-form-slot></div>
</dialog>

@vite(['resources/js/features/consultant-blog-sort.js', 'resources/js/features/consultant-blog-modal.js'])
@endsection
