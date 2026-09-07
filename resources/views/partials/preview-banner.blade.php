{{--
    Floating banner shown on any top-level page while a studio preview is
    active, so a tenant never mistakes unsaved preview styling for the live
    site. Only the previewer's own session carries the flag
    (ApplyPersonalTheme reads it), so this never appears for real visitors.
    Inside the studio's own preview iframe it is removed (see below): the
    pane is explicitly a preview, and the bar would only cover the site's
    nav that the tenant is trying to inspect.
--}}
@if(session()->has('studio.preview'))
    <div class="preview-banner" role="status">
        <span><i class="fas fa-eye" aria-hidden="true"></i> حالت پیش‌نمایش فعال است — این تغییرات هنوز ذخیره نشده‌اند.</span>
        @auth
            <form method="POST" action="{{ route('consultant.settings.appearance.preview.exit') }}" data-router="off">
                @csrf
                <button type="submit">خروج از پیش‌نمایش</button>
            </form>
        @endauth
    </div>
    <script>
        // The studio's live-preview pane embeds these pages in an iframe and
        // has its own exit control, so the banner is pure noise there. This
        // runs while the banner is still unpainted, so it never flashes.
        if (window.top !== window.self) {
            document.querySelectorAll('.preview-banner').forEach(function (b) { b.remove(); });
        }
    </script>
@endif
