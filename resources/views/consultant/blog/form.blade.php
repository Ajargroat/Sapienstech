{{--
    Blog create/edit form. This view is a bare fragment (no layout): the
    blog list injects it into a dialog via consultant-blog-modal.js, while
    consultant.blog.form-page wraps it in the shell for deep links and
    no-JS fallbacks. Keep everything the form needs inside this file.
--}}
@php
    $isEdit = $post->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('consultant.blog.update', $post) : route('consultant.blog.store') }}"
      enctype="multipart/form-data" class="settings-form blog-form" data-router="off">
    @csrf
    @if($isEdit) @method('PATCH') @endif

    {{-- Writing column: the post itself gets the room it deserves; status,
         cover and actions ride in a compact sidebar. --}}
    <div class="blog-form-main">
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

        {{-- Rich-text body (blog-editor.js). Without JS the textarea below
             stays the control (same as the old plain-text form); the editor
             hides it, unhides the contenteditable surface, and syncs HTML
             back into it on every change, so both worlds post name="body". --}}
        <div class="settings-field blog-editor" data-blog-editor
             data-media-url="{{ route('consultant.blog.media') }}"
             data-csrf="{{ csrf_token() }}">
            <span class="settings-field-label">متن</span>

            <div class="blog-editor-toolbar" role="toolbar" aria-label="ابزارهای نگارش">
                <button type="button" class="blog-editor-btn" data-cmd="undo" title="واگرد" aria-label="واگرد"><i class="fas fa-rotate-left" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-cmd="redo" title="ازنو" aria-label="ازنو"><i class="fas fa-rotate-right" aria-hidden="true"></i></button>
                <span class="blog-editor-sep" aria-hidden="true"></span>
                <button type="button" class="blog-editor-btn" data-state="bold" data-cmd="bold" title="پررنگ" aria-label="پررنگ"><i class="fas fa-bold" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-state="italic" data-cmd="italic" title="کج" aria-label="کج"><i class="fas fa-italic" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-state="underline" data-cmd="underline" title="زیرخط" aria-label="زیرخط"><i class="fas fa-underline" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-state="strikeThrough" data-cmd="strikeThrough" title="خط‌خورده" aria-label="خط‌خورده"><i class="fas fa-strikethrough" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-action="highlight" title="هایلایت" aria-label="هایلایت"><i class="fas fa-highlighter" aria-hidden="true"></i></button>
                <span class="blog-editor-sep" aria-hidden="true"></span>
                <select class="blog-editor-select" data-block-select aria-label="نوع بند" title="نوع بند">
                    <option value="p">متن معمولی</option>
                    <option value="h2">تیتر</option>
                    <option value="h3">زیرتیتر</option>
                    <option value="blockquote">نقل‌قول</option>
                </select>
                <span class="blog-editor-sep" aria-hidden="true"></span>
                <button type="button" class="blog-editor-btn" data-cmd="insertUnorderedList" title="فهرست نقطه‌ای" aria-label="فهرست نقطه‌ای"><i class="fas fa-list-ul" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-cmd="insertOrderedList" title="فهرست شماره‌دار" aria-label="فهرست شماره‌دار"><i class="fas fa-list-ol" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-action="table" title="درج جدول" aria-label="درج جدول"><i class="fas fa-table" aria-hidden="true"></i></button>
                <span class="blog-editor-sep" aria-hidden="true"></span>
                <button type="button" class="blog-editor-btn" data-align="align-right" data-justify="justifyRight" title="راست‌چین" aria-label="راست‌چین"><i class="fas fa-align-right" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-align="align-center" data-justify="justifyCenter" title="وسط‌چین" aria-label="وسط‌چین"><i class="fas fa-align-center" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-align="align-left" data-justify="justifyLeft" title="چپ‌چین" aria-label="چپ‌چین"><i class="fas fa-align-left" aria-hidden="true"></i></button>
                <span class="blog-editor-sep" aria-hidden="true"></span>
                <button type="button" class="blog-editor-btn" data-action="link" title="درج پیوند" aria-label="درج پیوند"><i class="fas fa-link" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-state="unlink" data-action="unlink" title="حذف پیوند" aria-label="حذف پیوند"><i class="fas fa-link-slash" aria-hidden="true"></i></button>
                <button type="button" class="blog-editor-btn" data-action="image" title="درج تصویر" aria-label="درج تصویر"><i class="fas fa-image" aria-hidden="true"></i></button>

                {{-- Image tools: revealed by the editor while a picture is selected. --}}
                <span class="blog-editor-img-tools">
                    <select class="blog-editor-select" data-size-select aria-label="اندازه تصویر" title="اندازه تصویر">
                        <option value="size-small">کوچک</option>
                        <option value="size-medium" selected>متوسط</option>
                        <option value="size-large">بزرگ</option>
                        <option value="size-full">تمام‌عرض</option>
                    </select>
                    <button type="button" class="blog-editor-btn blog-editor-btn--danger" data-action="remove-image" title="حذف تصویر" aria-label="حذف تصویر"><i class="fas fa-trash" aria-hidden="true"></i></button>
                </span>

                <span class="blog-editor-count" data-editor-count></span>
            </div>

            <div class="blog-editor-surface settings-input blog-form-body @error('body') is-invalid @enderror"
                 data-editor-surface contenteditable="true" role="textbox" aria-multiline="true"
                 aria-label="متن نوشته" hidden></div>

            <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden
                   data-editor-file>

            <textarea name="body" rows="14" data-editor-source
                      class="settings-input blog-form-body @error('body') is-invalid @enderror">{{ old('body', $post->editableBody()) }}</textarea>
            <span class="settings-hint">برای درج تصویر در میان متن، آن را بکشید و رها کنید یا از دکمهٔ تصویر استفاده کنید؛ سپس با انتخاب تصویر، جای‌گیری و اندازه‌اش را تنظیم کنید.</span>
            @error('body')<span class="settings-error">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="blog-form-side">
        {{-- Round switch instead of a dropdown: one obvious motion, and the
             hidden/checkbox pair keeps the plain "status" field the server
             expects (draft when the box is unchecked). --}}
        <div class="settings-field">
            <span class="settings-field-label">وضعیت</span>
            <label class="blog-status-toggle">
                <input type="hidden" name="status" value="draft">
                <input type="checkbox" name="status" value="published"
                       @checked(old('status', $post->status) === 'published')
                       aria-label="انتشار فوری نوشته">
                <span class="blog-status-switch" aria-hidden="true"></span>
                <span class="blog-status-state"></span>
            </label>
            <span class="settings-hint">پیش‌نویس فقط در پنل دیده می‌شود؛ با روشن‌کردن، نوشته در سایت عمومی منتشر می‌شود.</span>
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
            @include('consultant.partials.upload-tile', [
                'id' => $isEdit ? 'blog-cover-'.$post->id : 'blog-cover-new',
                'name' => 'cover',
                'text' => 'برای بارگذاری تصویر جلد کلیک کنید',
                'hint' => 'PNG، JPG یا WebP — حداکثر ۴ مگابایت',
            ])
            @error('cover')<span class="settings-error">{{ $message }}</span>@enderror
        </div>

        <div class="blog-form-actions">
            <button type="submit" class="primary-button">ذخیره</button>
            <a href="{{ route('consultant.blog.index') }}" class="secondary-button" data-blog-form-cancel>انصراف</a>
        </div>
    </div>
</form>
