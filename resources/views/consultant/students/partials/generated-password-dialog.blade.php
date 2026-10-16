{{--
    The one look at a new student's password.

    StudentEnrollment creates the account with a random password that is never
    stored in plain text, so the redirect the create/approve forms land on is
    the only moment it can exist. A flash strip can be scrolled past or reloaded
    away — this dialog opens itself over the page and refuses an accidental
    Escape until the password has at least been copied once.

    Included by the student profile (after creating by hand) and the request
    show page (after approving) — the only two pages that flash the password.
    The bundle lives inside this partial on purpose: the page router swaps
    #app-content and reads scripts only from the swapped region, so markup in
    the layout would never load its init.
--}}
@if(session('generated_password'))
    <dialog id="generated-password-dialog" class="exam-modal" aria-labelledby="generated-password-title">
        <div class="exam-modal-head">
            <h3 id="generated-password-title"><i class="fas fa-key" aria-hidden="true"></i> رمز عبور ساخته‌شده</h3>
            <p>
                این رمز تنها همین یک‌بار نمایش داده می‌شود و در سرور ذخیره نشده است؛
                پیش از بستن، آن را کپی کنید و به دانش‌آموز تحویل دهید.
            </p>
        </div>

        <div class="exam-modal-body">
            <div class="exam-field exam-field--wide">
                <label for="generated-password-username">نام کاربری (شناسهٔ ورود)</label>
                <input id="generated-password-username" type="text" dir="ltr" readonly
                       autocomplete="off" spellcheck="false"
                       value="{{ session('generated_password_for') }}">
            </div>
            <div class="exam-field exam-field--wide">
                <label for="generated-password-code">رمز عبور</label>
                <input id="generated-password-code" type="text" dir="ltr" readonly
                       autocomplete="off" spellcheck="false"
                       value="{{ session('generated_password') }}">
            </div>
        </div>

        <div class="exam-modal-foot">
            <button type="button" class="primary-button" data-generated-password-copy>
                <i class="fas fa-copy" aria-hidden="true"></i>
                <span data-generated-password-copy-label>کپی رمز</span>
            </button>
            <button type="button" class="secondary-button" data-generated-password-close>بستن</button>
        </div>
    </dialog>

    {{-- Without JS the dialog above can never open (a closed <dialog> is
         display:none), so the same credentials stay readable right on the
         page — the one request that carries them must not lose them. --}}
    <noscript>
        <div class="settings-flash settings-flash--success" role="status">
            <i class="fas fa-key" aria-hidden="true"></i>
            <span>
                رمز عبور ساخته‌شده — نام کاربری:
                <span dir="ltr">{{ session('generated_password_for') }}</span>،
                رمز عبور: <code dir="ltr">{{ session('generated_password') }}</code>.
                این رمز تنها همین یک‌بار نمایش داده می‌شود؛ آن را یادداشت کنید و به دانش‌آموز تحویل دهید.
            </span>
        </div>
    </noscript>

    @vite(['resources/js/features/consultant-password-reveal.js'])
@endif
