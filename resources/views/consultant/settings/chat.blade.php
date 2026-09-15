@extends('consultant.settings.layout')

@section('settings-content')
<div class="settings-cards">
    {{--
        Chat behavior settings — the curated subset of the `chat.*` /
        chat-feature config the tenant admin decides without a deploy
        (App\Http\Controllers\Consultant\Settings\ChatSettingsController →
        App\Support\ConfigWriter, the same runtime layer the Appearance
        studio writes). Staff see the panel read-only: values are tenant-
        wide, so publishing is admin-gated on the server regardless.
    --}}
    <section class="settings-card">
        @if(!$isTenantAdmin)
            <div class="settings-flash settings-flash--success" role="note">
                این تنظیمات سراسری هستند و فقط مدیر مجموعه می‌تواند آن‌ها را تغییر دهد.
            </div>
        @endif

        <form method="POST" action="{{ route('consultant.settings.chat.save') }}" data-router="off">
            @csrf

            <div class="chat-settings-grid">
                <label class="settings-field">
                    <span>نمای گفتگو برای پنل دانش‌آموز (features.student_chat)</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="features_student_chat" value="0">
                        <input type="checkbox" name="features_student_chat" value="1"
                               @checked($chatConfig['features.student_chat'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['features.student_chat'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>ساخت گروه دانش‌آموزان توسط مشاور</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="chat_groups_enabled" value="0">
                        <input type="checkbox" name="chat_groups_enabled" value="1"
                               @checked($chatConfig['chat.groups.enabled'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['chat.groups.enabled'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>حداکثر اعضای هر گروه</span>
                    <input type="number" name="chat_groups_max_members" class="settings-input"
                           min="2" max="500" value="{{ $chatConfig['chat.groups.max_members'] }}"
                           @disabled(! $isTenantAdmin)>
                </label>

                <label class="settings-field">
                    <span>پیوست فایل در پیام‌ها</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="chat_attachments_enabled" value="0">
                        <input type="checkbox" name="chat_attachments_enabled" value="1"
                               @checked($chatConfig['chat.attachments.enabled'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['chat.attachments.enabled'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>حداکثر حجم فایل (کیلوبایت)</span>
                    <input type="number" name="chat_attachments_max_kb" class="settings-input"
                           min="16" max="20480" value="{{ $chatConfig['chat.attachments.max_kb'] }}"
                           @disabled(! $isTenantAdmin)>
                </label>

                <label class="settings-field">
                    <span>نمایش علامت خوانده‌شدن پیام‌ها</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="chat_read_receipts" value="0">
                        <input type="checkbox" name="chat_read_receipts" value="1"
                               @checked($chatConfig['chat.read_receipts'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['chat.read_receipts'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>نمایش «در حال نوشتن…»</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="chat_typing_indicator" value="0">
                        <input type="checkbox" name="chat_typing_indicator" value="1"
                               @checked($chatConfig['chat.typing_indicator'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['chat.typing_indicator'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>امکان بستن/بازکردن گفتگو توسط مشاور</span>
                    <label class="studio-toggle">
                        <input type="hidden" name="chat_close_threads" value="0">
                        <input type="checkbox" name="chat_close_threads" value="1"
                               @checked($chatConfig['chat.close_threads'])
                               @disabled(! $isTenantAdmin)>
                        <span>{{ $chatConfig['chat.close_threads'] ? 'روشن' : 'خاموش' }}</span>
                    </label>
                </label>

                <label class="settings-field">
                    <span>حداکثر طول هر پیام (کاراکتر)</span>
                    <input type="number" name="chat_message_max_length" class="settings-input"
                           min="100" max="20000" value="{{ $chatConfig['chat.message_max_length'] }}"
                           @disabled(! $isTenantAdmin)>
                </label>

                <label class="settings-field">
                    <span>مهلت ویرایش پیام (دقیقه — پیش‌فرض ۱۰۰۸۰ = یک هفته؛ خالی = پیش‌فرض)</span>
                    <input type="number" name="chat_edit_window_minutes" class="settings-input"
                           min="1" max="10080" placeholder="10080"
                           value="{{ $chatConfig['chat.edit_window_minutes'] ?: '' }}"
                           @disabled(! $isTenantAdmin)>
                </label>

                <label class="settings-field">
                    <span>سقف ارسال پیام در دقیقه (هر گفتگو)</span>
                    <input type="number" name="chat_rate_limit_per_minute" class="settings-input"
                           min="1" max="120" value="{{ $chatConfig['chat.rate_limit_per_minute'] }}"
                           @disabled(! $isTenantAdmin)>
                </label>

                <label class="settings-field">
                    <span>بستن خودکار گفتگوهای بی‌فعال (روز، ۰ = خاموش)</span>
                    <input type="number" name="chat_idle_autoclose_days" class="settings-input"
                           min="0" max="365" value="{{ $chatConfig['chat.idle_autoclose_days'] }}"
                           @disabled(! $isTenantAdmin)>
                </label>
            </div>

            <label class="settings-field">
                <span>متن خوش‌آمد (نمایش در حالت خالی گفتگو)</span>
                <textarea name="chat_greeting_text" rows="2" class="settings-input"
                          @disabled(! $isTenantAdmin)>{{ $chatConfig['chat.greeting_text'] }}</textarea>
            </label>

            <label class="settings-field">
                <span>متن جای‌نویس فیلد پیام</span>
                <input type="text" name="chat_placeholder_text" class="settings-input"
                       value="{{ $chatConfig['chat.placeholder_text'] }}"
                       @disabled(! $isTenantAdmin)>
            </label>

            @if($isTenantAdmin)
                <div style="margin-top:1rem">
                    <button type="submit" class="primary-button">ذخیره تنظیمات</button>
                </div>
            @endif
        </form>
    </section>

    <section class="settings-card">
        <h3 class="settings-card-title">ظاهر گفتگو</h3>
        <p class="settings-help">
            رنگ، قلم و شکل حباب پیام‌ها از همان پوسته‌ی tenant شما می‌آید؛ برای تغییر
            ظاهر گفتگو به <a href="{{ route('consultant.settings.profile', ['tab' => 'appearance']) }}">استودیوی ظاهر</a>
            بروید (گروه «گفتگو» در همان‌جا رفتار ظاهری را هم تنظیم می‌کند).
        </p>
    </section>
</div>
@endsection

<style>
    /* Scoped to this settings page only (the layout has no styles stack). */
    .chat-settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
        gap: .4rem 1.4rem;
    }
    .settings-field { display: flex; flex-direction: column; gap: .35rem; margin-block: .55rem; }
    .settings-field > span:first-child { font-size: .82rem; color: var(--c-muted); }
    .settings-help { font-size: .85rem; color: var(--c-muted); line-height: 1.9; }
    .settings-help a { color: var(--c-link, var(--c-primary)); }
</style>
