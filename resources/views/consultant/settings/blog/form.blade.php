@extends('consultant.settings.layout')

@php
    $isEdit = $post->exists;
@endphp

@section('settings-content')
<div class="settings-cards">
    <section class="settings-card">
        <h3 class="settings-card-title">{{ $isEdit ? 'ویرایش نوشته' : 'نوشتهٔ جدید' }}</h3>

        <form method="POST"
              action="{{ $isEdit ? route('consultant.settings.blog.update', $post) : route('consultant.settings.blog.store') }}"
              enctype="multipart/form-data" class="settings-form blog-form" data-router="off">
            @csrf
            @if($isEdit) @method('PATCH') @endif

            <label class="settings-field">
                <span class="settings-field-label">عنوان</span>
                <input type="text" name="title" value="{{ old('title', $post->title) }}" required
                       class="settings-input @error('title') is-invalid @enderror">
                @error('title')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">چکیده</span>
                <textarea name="excerpt" rows="2" class="settings-input @error('excerpt') is-invalid @enderror">{{ old('excerpt', $post->excerpt) }}</textarea>
                <span class="settings-hint">اگر خالی بماند، ابتدای متن نوشته استفاده می‌شود.</span>
                @error('excerpt')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <label class="settings-field">
                <span class="settings-field-label">متن</span>
                <textarea name="body" rows="12" class="settings-input @error('body') is-invalid @enderror">{{ old('body', $post->body) }}</textarea>
                @error('body')<span class="settings-error">{{ $message }}</span>@enderror
            </label>

            <div class="settings-field-row">
                <label class="settings-field">
                    <span class="settings-field-label">وضعیت</span>
                    <select name="status" class="settings-input">
                        <option value="draft" @selected(old('status', $post->status) === 'draft')>پیش‌نویس</option>
                        <option value="published" @selected(old('status', $post->status) === 'published')>منتشرشده</option>
                    </select>
                </label>

                <label class="settings-field">
                    <span class="settings-field-label">ترتیب نمایش</span>
                    <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $post->sort_order ?? 0) }}" class="settings-input">
                </label>
            </div>

            <div class="settings-field">
                <span class="settings-field-label">تصویر جلد (اختیاری)</span>
                @if($post->cover_image_path)
                    <div class="blog-cover-preview">
                        <img src="{{ tenant_asset($post->cover_image_path) }}" alt="">
                        <label class="blog-cover-remove">
                            <input type="checkbox" name="remove_cover" value="1"> حذف تصویر فعلی
                        </label>
                    </div>
                @endif
                <input type="file" name="cover" accept="image/*" class="settings-file">
                @error('cover')<span class="settings-error">{{ $message }}</span>@enderror
            </div>

            <div class="blog-form-actions">
                <button type="submit" class="primary-button">ذخیره</button>
                <a href="{{ route('consultant.settings.blog.index') }}" class="secondary-button">انصراف</a>
            </div>
        </form>
    </section>
</div>
@endsection
