<?php

/*
|--------------------------------------------------------------------------
| Appearance Studio — the editable surface of a tenant's config
|--------------------------------------------------------------------------
|
| This is the security boundary of the theme studio, not just a form
| definition. Only paths declared here are writable through the studio;
| every value is validated against its `rules` before it reaches the DB or a
| user's preferences. Because these values flow straight into CSS custom
| properties and Blade, an unlisted path or an unvalidated value would be an
| injection vector — so the studio can never write anything this file does
| not explicitly allow.
|
| Adding a lever to the studio == adding one entry here. The controller,
| validation and rendering are all generic over this shape.
|
| Deliberately NOT listed:
|   - derived tokens (hovers, borders, glass, shadows, gradients, spacing) —
|     ThemeTokens recomputes them from the primitives, and editing both ends
|     of a derivation only creates confusion;
|   - list-valued content that the `list` control does not cover yet — rows
|     carrying image uploads (blog/logos items) or nested cell tables
|     (comparison rows) stay file-owned until those shapes fit the control;
|   - theme.custom.css and theme.assets.* — raw CSS/URLs are platform-admin
|     escape hatches, never tenant-facing;
|   - dead levers (brand.position, buttons.hover, i18n.calendar/date_format) —
|     nothing consumes them yet; exposing them would promise an effect.
|
| Reset semantics: every scalar field is `required` because the resolved tree
| never holds nulls for them (ThemeTokens fills the rest), and clearing a
| nullable field submits "" which StudioSchema::normalize turns back into
| null — i.e. forgetting the override so the tenant file / archetype shows
| through again. The per-field reset button does the same explicitly.
|
| Field keys:
|   path     dotted path into the resolved site() tree (also the write target)
|   label    Persian field label
|   hint     optional one-line Persian explanation rendered as an ⓘ bubble
|            next to the label (hover on desktop, tap on touch)
|   control  color | text | textarea | select | toggle | number | range | font | image | sections | archetype | list
|   rules    Laravel validation rules for the scalar value (for list: the
|            whole-array rules; row values validate via `item` rules)
|   options  [value => label] for select/sections
|   item     list control: the per-row definitions (key, label, control,
|            rules, options). Only these keys survive normalization — an
|            unknown key in a submitted row is dropped. A row whose non-toggle
|            fields are all empty is dropped before validation, so an
|            abandoned "add row" is a no-op; clearing every row forgets the
|            whole list override and the file-owned items show through again
|            (hiding one item is what its `visible` toggle is for).
|            A list may declare one item def `discriminant` (the row's type
|            select) and mark other defs `show_for` => [types] — cells that
|            only apply to those types. Hidden-for-the-type cells still
|            submit, but are neither validated (StudioSaveRequest builds
|            concrete per-row rules; this Laravel resolves required_if
|            parameters literally) nor stored (normalizeList drops them).
|            `required_for` => [types] on a show_for def makes the cell
|            required exactly for those row types. A type with no content
|            cells (spacer, divider) is never treated as an abandoned row.
|   max      list control: the most rows a submission may carry
|   locked   sections control: option keys pinned to their canonical slot
|            (their index in `options`). They can never be moved or hidden —
|            StudioSchema::pinSections() re-pins them on every render and
|            write, so the invariant survives forged or stale submissions.
|   min/max/step/unit
|            range control: the slider bounds and the unit suffix composed
|            onto its number. The bar only writes into the named text input,
|            so `rules` stay the single source of truth for what reaches the
|            DB; a stored value carrying a different unit keeps its own unit.
|   folder   image control: upload subfolder under the tenant's tree
|   admin    when true, the field is tenant_admin-only regardless of the
|            staff-publish flag (used for the feature switches)
|
| Group keys:
|   label    Persian section label
|   icon     Font Awesome class shown in the section header and tab
|   hint     optional explanation shown as an ⓘ bubble next to the title
|   admin    when true, the whole group is tenant_admin-only
|
| Studio UX contract (rendered generically from the shape above):
|   - the settings live in a tabbed inspector rail: one tab per group, only
|     the active group is open, the choice persists per browser, and a
|     failed save reopens the rail on the group holding the error;
|   - with the live preview on (wide screens) the preview becomes the page's
|     main content and the rail docks beside it as its own scrolling bar;
|   - a `hint` on a group or field renders an ⓘ whose bubble explains the
|     option on hover (desktop) or tap (touch);
|   - live preview: editing any field re-renders the site preview without a
|     save. Paths that publish as CSS custom properties (ThemeTokens::VARS)
|     re-theme the preview instantly; everything else reloads it. See
|     StudioSchema::liveMode(). The preview emulates real viewports: a
|     desktop/mobile device switch renders the iframe at the device width
|     (scaled to fit the pane), and a maximize button widens the pane.
|
*/

// Rule fragments reused across dozens of fields. Every one is a whitelist:
// anything that reaches a CSS custom property must match its shape exactly.
$hex     = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
$len     = ['required', 'string', 'max:20', 'regex:/^[0-9.]+(px|rem|em|%)$/'];
$lenView = ['required', 'string', 'max:20', 'regex:/^[0-9.]+(px|rem|em|%|svh|vh|dvh)$/'];
$num     = ['required', 'string', 'max:10', 'regex:/^[0-9.]+$/'];
$dur     = ['required', 'string', 'max:10', 'regex:/^[0-9.]+(ms|s)$/'];
$ls      = ['required', 'string', 'max:20', 'regex:/^-?[0-9.]+(em|px|rem)?$/'];
$lh      = ['required', 'string', 'max:20', 'regex:/^([0-9.]+|inherit|normal)$/'];
$clamp   = ['required', 'string', 'max:60', 'regex:/^[0-9a-zA-Z(),.\s%+-]+$/'];
$font    = ['required', 'string', 'regex:/^[A-Za-z0-9 ,_\-]{1,80}$/'];
$bool    = ['nullable', 'boolean'];

$weights = [
    'options' => ['300' => '۳۰۰', '400' => '۴۰۰', '500' => '۵۰۰', '600' => '۶۰۰', '700' => '۷۰۰', '800' => '۸۰۰', '900' => '۹۰۰'],
    'in'      => 'in:300,400,500,600,700,800,900',
];

// Shared option lists and rule fragments for the list control's item defs.
// Every select here stays a whitelist: its value reaches a CSS class name or
// a route() call, so free text is never acceptable.
$accents = [
    'primary'       => 'رنگ اصلی',
    'secondary'     => 'رنگ ثانویه',
    'accent_blue'   => 'تأکید آبی',
    'accent_emerald' => 'تأکید زمردی',
    'accent_orange' => 'تأکید نارنجی',
    'accent_teal'   => 'تأکید فیروزه‌ای',
    'accent_red'    => 'تأکید قرمز',
    'accent_violet' => 'تأکید بنفش',
    'accent_pink'   => 'تأکید صورتی',
    'accent_lime'   => 'تأکید لیمویی',
    'accent_cyan'   => 'تأکید آبی آسمانی',
    'accent_amber'  => 'تأکید کهربایی',
    'accent_rose'   => 'تأکید گل‌سرخی',
];
$accentsIn  = 'in:'.implode(',', array_keys($accents));
$href       = ['required', 'string', 'max:2048', 'regex:/^(#|\/|https?:\/\/)[A-Za-z0-9 ._\/?%&#=:;\-]*$/'];
$faClass    = ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/'];
$btnStyle   = ['solid' => 'پر', 'ghost' => 'شیشه‌ای کم‌رنگ', 'primary' => 'برند', 'outline' => 'خطی'];
$btnIcon    = ['' => 'پیش‌فرض تم', 'none' => 'بدون', 'arrow' => 'پیکان (جهت متن)', 'arrow-ltr' => 'پیکان راست', 'arrow-down' => 'پیکان پایین', 'chevron' => 'شیور', 'plus' => 'بعلاوه', 'sparkle' => 'درخشش', 'download' => 'دانلود', 'play' => 'پخش'];
$siteRoutes = ['login' => 'صفحه ورود', 'home' => 'صفحه اصلی', 'about' => 'درباره ما', 'contact' => 'تماس', 'student.login' => 'ورود دانش‌آموز'];

// Typed-block variants of the shared fragments. The leading '' option on
// every block select matters twice over: it keeps an untouched select
// diff-free against a baseline row that omits the key (the radio-chip
// contract always submits something), and normalizeList drops '' so the
// renderer falls back to the theme default.
$hrefOpt    = array_merge(['nullable'], array_slice($href, 1));
$btnStyleOpt = ['' => 'پیش‌فرض تم'] + $btnStyle;
$accentsOpt  = ['' => 'پیش‌فرض'] + $accents;
$alignOpt    = ['' => 'خودکار', 'start' => 'ابتدا', 'center' => 'وسط', 'end' => 'پایان'];
$imgSrc      = ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)[A-Za-z0-9 ._\/?%&#=-]+$/'];
$blockTypes  = [
    'heading' => 'تیتر', 'text' => 'متن', 'button' => 'دکمه', 'card' => 'کارت',
    'image' => 'تصویر', 'spacer' => 'فاصله', 'divider' => 'خط جدا',
];

return [

    'groups' => [

        'brand' => [
            'label' => 'برند',
            'icon'  => 'fa-fingerprint',
            'fields' => [
                ['path' => 'theme.archetype', 'label' => 'آرکیتایپ (هویت بصری آماده)', 'hint' => 'رنگ، قلم و جنس سطح را یک‌جا از یک هویت آماده انتخاب می‌کند؛ تغییرات دستی شما روی همان سوار می‌شوند.', 'control' => 'archetype', 'rules' => ['required', 'string']],
                ['path' => 'tenant.name', 'label' => 'نام مجموعه', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.short_name', 'label' => 'نام کوتاه', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.page_title', 'label' => 'عنوان صفحه', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'tenant.role_label', 'label' => 'برچسب پنل در نوار بالا', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.favicon', 'label' => 'آیکون سایت (URL)', 'hint' => 'آیکون کوچک تب مرورگر؛ مسیر /storage/… یا نشانی کامل https.', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)[A-Za-z0-9 ._\/?%&#=-]+$/']],
                ['path' => 'theme.brand.src', 'label' => 'تصویر لوگو', 'control' => 'image', 'folder' => 'brand', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'theme.brand.variant', 'label' => 'نحوه نمایش برند', 'hint' => 'نحوهٔ چیدمان نشان و نام برند در سربرگ سایت و پنل.', 'control' => 'select', 'rules' => ['nullable', 'in:mark,wordmark,mark+word,stacked'],
                    'options' => ['mark' => 'فقط نشان', 'wordmark' => 'فقط متن', 'mark+word' => 'نشان و متن', 'stacked' => 'عمودی']],
                ['path' => 'theme.brand.height', 'label' => 'ارتفاع لوگو', 'hint' => 'قد لوگو؛ عرض و فاصله‌ها خودکار متناسب می‌شوند.', 'control' => 'range', 'min' => 1, 'max' => 8, 'step' => 0.25, 'unit' => 'rem', 'rules' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+(rem|px|em)$/']],
            ],
        ],

        'colors' => [
            'label' => 'رنگ‌ها',
            'icon'  => 'fa-palette',
            'fields' => [
                ['path' => 'theme.colors.primary', 'label' => 'رنگ اصلی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.secondary', 'label' => 'رنگ ثانویه', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.background', 'label' => 'پس‌زمینه', 'hint' => 'زمینهٔ اصلی همهٔ صفحات.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.surface', 'label' => 'سطح', 'hint' => 'رنگ کارت‌ها، پنل‌ها و فرم‌ها.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.surface_alt', 'label' => 'سطح جایگزین', 'hint' => 'زمینهٔ دوم؛ برای بخش‌های یکی‌درمیان و سطوح فرعی.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.text', 'label' => 'متن', 'hint' => 'رنگ متن اصلی؛ با «پس‌زمینه» کنتراست کافی داشته باشد.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.heading', 'label' => 'تیترها', 'hint' => 'رنگ تیترها؛ اگر هم‌رنگ متن می‌خواهید، همان مقدار را بگذارید.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.link', 'label' => 'لینک‌ها', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.success', 'label' => 'موفقیت', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.info', 'label' => 'اطلاع', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.warning', 'label' => 'هشدار', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.danger', 'label' => 'خطر', 'control' => 'color', 'rules' => $hex],

                // The accent palette is content-addressable: any item in the
                // section configs can name these (accent => 'accent-violet').
                ['path' => 'theme.colors.accent_blue', 'label' => 'تأکید آبی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_emerald', 'label' => 'تأکید زمردی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_orange', 'label' => 'تأکید نارنجی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_teal', 'label' => 'تأکید فیروزه‌ای', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_red', 'label' => 'تأکید قرمز', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_violet', 'label' => 'تأکید بنفش', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_pink', 'label' => 'تأکید صورتی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_lime', 'label' => 'تأکید لیمویی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_cyan', 'label' => 'تأکید آبی آسمانی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_amber', 'label' => 'تأکید کهربایی', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_rose', 'label' => 'تأکید گل‌سرخی', 'control' => 'color', 'rules' => $hex],

                // The light colour-scheme the visitor toggle can switch to.
                ['path' => 'theme.schemes.light.background', 'label' => 'روشن: پس‌زمینه', 'hint' => 'حالت روشن همان چیزی است که بازدیدکننده با سوییچ تم می‌بیند؛ بقیهٔ رنگ‌های این حالت خودکار ساخته می‌شود.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface', 'label' => 'روشن: سطح', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface_alt', 'label' => 'روشن: سطح جایگزین', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface_elevated', 'label' => 'روشن: سطح برجسته', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.text', 'label' => 'روشن: متن', 'control' => 'color', 'rules' => $hex],
            ],
        ],

        'typography' => [
            'label' => 'تایپوگرافی',
            'icon'  => 'fa-font',
            'fields' => [
                ['path' => 'theme.typography.font_family', 'label' => 'قلم متن', 'hint' => 'نام قلم متن بدنه در سراسر سایت و پنل.', 'control' => 'font', 'rules' => ['required', 'string', 'regex:/^[A-Za-z0-9 _\-]{1,60}$/']],
                ['path' => 'theme.typography.font_heading', 'label' => 'قلم تیترها', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_accent', 'label' => 'قلم تأکیدی', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_button', 'label' => 'قلم دکمه‌ها', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_mono', 'label' => 'قلم مونو', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.body_size', 'label' => 'اندازه متن بدنه', 'hint' => 'اندازهٔ پایهٔ متن؛ تیترها اندازهٔ جداگانه دارند.', 'control' => 'range', 'min' => 10, 'max' => 28, 'step' => 0.5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.typography.body_weight', 'label' => 'ضخامت متن بدنه', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.typography.heading_weight', 'label' => 'ضخامت تیتر', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.typography.heading_transform', 'label' => 'حروف تیتر', 'control' => 'select',
                    'rules' => ['required', 'in:none,uppercase,capitalize,lowercase'],
                    'options' => ['none' => 'عادی', 'uppercase' => 'بزرگ', 'capitalize' => 'سرِ حرف بزرگ', 'lowercase' => 'کوچک']],
                ['path' => 'theme.typography.heading_letter_spacing', 'label' => 'فاصله حروف تیتر', 'control' => 'range', 'min' => -0.1, 'max' => 0.2, 'step' => 0.005, 'unit' => 'em', 'rules' => $ls],
                ['path' => 'theme.typography.heading_line_height', 'label' => 'فاصله خطوط تیتر', 'control' => 'range', 'min' => 0.8, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.heading_balance', 'label' => 'تعادل‌بندی خطوط تیتر', 'hint' => 'سعی می‌کند طول خطوط تیترها یکنواخت باشد؛ پشتیبانی مرورگرها متفاوت است.', 'control' => 'select',
                    'rules' => ['required', 'in:auto,balance,pretty'],
                    'options' => ['auto' => 'خودکار', 'balance' => 'متوازن', 'pretty' => 'زیوارو']],
                ['path' => 'theme.typography.h1_size', 'label' => 'اندازه H1', 'hint' => 'مقدار clamp یعنی «حداقل، متناسب با عرض صفحه، حداکثر».', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.h2_size', 'label' => 'اندازه H2', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.h3_size', 'label' => 'اندازه H3', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.stat_size', 'label' => 'اندازه اعداد آماری', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.hero_line_height', 'label' => 'فاصله خطوط تیتر ویترین', 'control' => 'range', 'min' => 0.8, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.letter_spacing', 'label' => 'فاصله حروف متن', 'hint' => 'اعداد منفی حروف را به هم می‌چسبانند و مثبت آن‌ها را باز می‌کند.', 'control' => 'range', 'min' => -0.05, 'max' => 0.2, 'step' => 0.005, 'unit' => '', 'rules' => $ls],
                ['path' => 'theme.typography.line_height', 'label' => 'فاصله خطوط متن', 'control' => 'range', 'min' => 1, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.measure', 'label' => 'عرض ایده‌آل پاراگراف', 'hint' => 'حداکثر عرض پاراگراف؛ هرچه باریک‌تر، خواندن راحت‌تر.', 'control' => 'range', 'min' => 20, 'max' => 100, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
            ],
        ],

        'surface' => [
            'label' => 'سطح و عمق',
            'icon'  => 'fa-layer-group',
            'fields' => [
                ['path' => 'theme.surface.material', 'label' => 'جنس سطح', 'hint' => 'جنس سطح کارت‌ها و پنل‌ها؛ «شیشه‌ای» پشت کارت را محو می‌کند.', 'control' => 'select', 'rules' => ['nullable', 'in:glass,acrylic,matte,paper,flat'],
                    'options' => ['glass' => 'شیشه‌ای', 'acrylic' => 'اکریلیک', 'matte' => 'مات', 'paper' => 'کاغذی', 'flat' => 'تخت']],
                ['path' => 'theme.surface.blur', 'label' => 'محوِ شیشه', 'hint' => 'میزان محو زیر سطوح شیشه‌ای و اکریلیک.', 'control' => 'range', 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.surface.opacity', 'label' => 'شفافیت سطح (۰ تا ۱)', 'hint' => 'میزان عبور نور از کارت‌ها؛ ۱ یعنی کاملاً مات.', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.surface.noise', 'label' => 'دانه‌دانه‌بودن سطح (۰ تا ۱)', 'hint' => 'بافت دانه‌ای ریز روی سطح، برای حس کاغذی.', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.surface.specular', 'label' => 'بازتاب نور سطح', 'control' => 'text', 'rules' => ['required', 'string', 'max:80', 'regex:/^[0-9a-zA-Z(),.\s%+-]+$/']],
                ['path' => 'theme.surface.inner_border', 'label' => 'حاشیه داخلی سطح', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.depth.elevation', 'label' => 'مدل ارتفاع', 'hint' => 'سبک سایه‌ها؛ «نئون» برای تم‌های تیره درخشان‌تر است.', 'control' => 'select', 'rules' => ['required', 'in:flat,ambient,soft,medium,heavy,neon,brutal'],
                    'options' => ['flat' => 'تخت', 'ambient' => 'محیطی', 'soft' => 'نرم', 'medium' => 'متوسط', 'heavy' => 'سنگین', 'neon' => 'نئون', 'brutal' => 'بروتال']],
                ['path' => 'theme.depth.shadow_tint', 'label' => 'سایه رنگی (برند)', 'hint' => 'سایه‌ها به‌جای خاکستری، ته‌رنگ برند می‌گیرند.', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'shape' => [
            'label' => 'گردی و خطوط',
            'icon'  => 'fa-vector-square',
            'fields' => [
                ['path' => 'theme.shape.radius_scale', 'label' => 'مقیاس گردی (یک‌جا)', 'hint' => 'همهٔ گردی‌های تنظیم‌نشده را یک‌جا با یک مقیاس آماده پر می‌کند؛ مقادیری که خودتان دستی گذاشته‌اید حفظ می‌شوند.', 'control' => 'select', 'rules' => ['nullable', 'in:auto,sharp,slight,soft,round,pill'],
                    'options' => ['auto' => 'خودکار', 'sharp' => 'تیز', 'slight' => 'کم', 'soft' => 'نرم', 'round' => 'گرد', 'pill' => 'کپسولی']],
                ['path' => 'theme.shape.radius_sm', 'label' => 'گردی کوچک', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.radius_md', 'label' => 'گردی متوسط', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.radius_lg', 'label' => 'گردی بزرگ', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.radius_xl', 'label' => 'گردی خیلی بزرگ', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.radius_2xl', 'label' => 'گردی فوق‌بزرگ', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.button_radius', 'label' => 'گردی دکمه', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.input_radius', 'label' => 'گردی ورودی‌ها', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.card_radius', 'label' => 'گردی کارت‌ها', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.sidebar_radius', 'label' => 'گردی نوار کناری', 'control' => 'text', 'rules' => $len],
                ['path' => 'theme.shape.border_width', 'label' => 'ضخامت حاشیه‌ها', 'control' => 'range', 'min' => 0, 'max' => 8, 'step' => 1, 'unit' => 'px', 'rules' => $len],
            ],
        ],

        'layout' => [
            'label' => 'چیدمان و اندازه‌ها',
            'icon'  => 'fa-columns',
            'fields' => [
                ['path' => 'theme.scale.density', 'label' => 'تراکم', 'hint' => 'فاصله‌گذاری کل رابط را یک‌جا کم یا زیاد می‌کند.', 'control' => 'select', 'rules' => ['required', 'in:compact,normal,spacious'],
                    'options' => ['compact' => 'فشرده', 'normal' => 'عادی', 'spacious' => 'رها']],
                ['path' => 'theme.scale.container_width', 'label' => 'عرض محتوا', 'hint' => 'عرض ستون اصلی محتوای صفحات.', 'control' => 'select', 'rules' => ['required', 'in:narrow,content,wide,full'],
                    'options' => ['narrow' => 'باریک', 'content' => 'استاندارد', 'wide' => 'عریض', 'full' => 'تمام‌عرض']],
                ['path' => 'theme.scale.section_rhythm', 'label' => 'فاصله بین بخش‌ها', 'hint' => 'فضای عمودی بین بخش‌های صفحهٔ اصلی.', 'control' => 'range', 'min' => 2, 'max' => 16, 'step' => 0.5, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.layout.hero_ratio', 'label' => 'نسبت ستون‌های ویترین', 'hint' => 'تقسیم عرض بین ستون متن و تصویر در ویترین.', 'control' => 'select', 'rules' => ['required', 'in:50-50,60-40,40-60,70-30,30-70'],
                    'options' => ['50-50' => '۵۰-۵۰', '60-40' => '۶۰-۴۰', '40-60' => '۴۰-۶۰', '70-30' => '۷۰-۳۰', '30-70' => '۳۰-۷۰']],
                ['path' => 'theme.layout.card_gap', 'label' => 'فاصله کارت‌ها', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.content_max_width', 'label' => 'حداکثر عرض محتوا', 'control' => 'range', 'min' => 640, 'max' => 1920, 'step' => 20, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.content_padding', 'label' => 'حاشیه محتوا', 'control' => 'range', 'min' => 0, 'max' => 96, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.sidebar_width', 'label' => 'عرض نوار کناری پنل', 'control' => 'range', 'min' => 160, 'max' => 400, 'step' => 4, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.topnav_height', 'label' => 'ارتفاع نوار بالای پنل', 'control' => 'range', 'min' => 40, 'max' => 120, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.shell_nav', 'label' => 'جای منوی پنل', 'hint' => 'ساختار ناوبری پنل: نوار افقی بالا یا ستون کناری.', 'control' => 'select', 'rules' => ['required', 'in:topnav,sidebar'],
                    'options' => ['topnav' => 'نوار بالا', 'sidebar' => 'نوار کناری']],
                ['path' => 'theme.landing.nav_height', 'label' => 'ارتفاع منوی سایت', 'control' => 'range', 'min' => 2.5, 'max' => 8, 'step' => 0.25, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.landing.hero_min_height', 'label' => 'حداقل ارتفاع ویترین', 'hint' => 'svh یعنی درصد ارتفاع صفحهٔ نمایش؛ ویترین کوتاه‌تر از این نمی‌شود.', 'control' => 'range', 'min' => 40, 'max' => 100, 'step' => 1, 'unit' => 'svh', 'rules' => $lenView],
            ],
        ],

        'background' => [
            'label' => 'پس‌زمینه',
            'icon'  => 'fa-image',
            'fields' => [
                ['path' => 'theme.background.mode', 'label' => 'حالت پس‌زمینه', 'hint' => 'نقش اصلی پشت کل صفحه؛ بعضی حالت‌ها با رنگ‌های برند ساخته می‌شوند.', 'control' => 'select', 'rules' => ['required', 'in:glow,flat,gradient,mesh,grid,dots,noise,beams,aurora,stripes'],
                    'options' => ['glow' => 'درخشش', 'flat' => 'تخت', 'gradient' => 'گرادیان', 'mesh' => 'تور', 'grid' => 'شبکه', 'dots' => 'نقطه', 'noise' => 'نویز', 'beams' => 'پرتو', 'aurora' => 'شفق', 'stripes' => 'نوار‌نوار']],
                ['path' => 'theme.background.gradient_angle', 'label' => 'زاویه گرادیان', 'control' => 'range', 'min' => 0, 'max' => 360, 'step' => 1, 'unit' => 'deg', 'rules' => ['required', 'string', 'max:10', 'regex:/^[0-9]{1,3}deg$/']],
                ['path' => 'theme.background.section_alternation', 'label' => 'یک‌درمیان‌سازی بخش‌ها', 'hint' => 'بخش‌های یکی‌درمیان صفحهٔ اصلی زمینهٔ متفاوت می‌گیرند.', 'control' => 'select', 'rules' => ['required', 'in:none,tint,surface-alt,rule'],
                    'options' => ['none' => 'بدون', 'tint' => 'تغییر رنگ', 'surface-alt' => 'سطح جایگزین', 'rule' => 'خط جداکننده']],
                ['path' => 'theme.background.noise', 'label' => 'نویز پس‌زمینه (۰ تا ۱)', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.background.grid_size', 'label' => 'اندازه خانه شبکه', 'control' => 'range', 'min' => 8, 'max' => 120, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.glow_size', 'label' => 'اندازه هاله', 'hint' => 'اندازهٔ هالهٔ نورانی پشت محتوای ویترین.', 'control' => 'range', 'min' => 4, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.landing.glow_blur', 'label' => 'محو هاله', 'control' => 'range', 'min' => 0, 'max' => 300, 'step' => 5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.glow_opacity', 'label' => 'شدت هاله (۰ تا ۱)', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
            ],
        ],

        'motion' => [
            'label' => 'حرکت و انیمیشن',
            'icon'  => 'fa-wind',
            'fields' => [
                ['path' => 'theme.motion.reveal', 'label' => 'نمایش هنگام اسکرول', 'hint' => 'نحوهٔ ورود بخش‌ها وقتی با اسکرول وارد دید می‌شوند.', 'control' => 'select',
                    'rules' => ['required', 'in:fade-up,fade-down,fade-left,fade-right,zoom,blur-in,line-mask,rise,drop,flip,wipe,skew-in,none'],
                    'options' => ['fade-up' => 'محو از پایین', 'fade-down' => 'محو از بالا', 'fade-left' => 'محو از چپ', 'fade-right' => 'محو از راست',
                        'zoom' => 'زوم', 'blur-in' => 'محو', 'line-mask' => 'خطی', 'rise' => 'برآمدن', 'drop' => 'فرود', 'flip' => 'چرخش سه‌بعدی',
                        'wipe' => 'پاک‌شویی', 'skew-in' => 'کج‌ورودی', 'none' => 'هیچ']],
                ['path' => 'theme.motion.stagger', 'label' => 'ترتیب نمایش کارت‌ها', 'control' => 'select', 'rules' => ['required', 'in:sequential,none'],
                    'options' => ['sequential' => 'پله‌ای', 'none' => 'همزمان']],
                ['path' => 'theme.motion.parallax', 'label' => 'پارالاکس', 'hint' => 'حرکت آهستهٔ المان‌های تزئینی هنگام اسکرول.', 'control' => 'select', 'rules' => ['required', 'in:none,subtle,strong'],
                    'options' => ['none' => 'بدون', 'subtle' => 'ملایم', 'strong' => 'قوی']],
                ['path' => 'theme.motion.duration_scale', 'label' => 'ضریب سرعت انیمیشن', 'hint' => 'ضریب زمان همهٔ انیمیشن‌ها؛ عدد بزرگ‌تر یعنی آهسته‌تر.', 'control' => 'range', 'min' => 0.25, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.motion.easing', 'label' => 'منحنی حرکت', 'control' => 'text', 'rules' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9(),.\s-]+$/']],
                ['path' => 'theme.motion.hover', 'label' => 'هاور کارت‌ها', 'hint' => 'واکنش کارت‌ها به رفتن ماوس روی آن‌ها.', 'control' => 'select',
                    'rules' => ['required', 'in:lift,glow,scale,border,skew,shadow-grow,shift,icon-spin,invert,underline,none'],
                    'options' => ['lift' => 'بلند شدن', 'glow' => 'درخشش', 'scale' => 'بزرگ‌نمایی', 'border' => 'حاشیه', 'skew' => 'کجی',
                        'shadow-grow' => 'رشد سایه', 'shift' => 'جابه‌جایی', 'icon-spin' => 'چرخش آیکون', 'invert' => 'وارونگی', 'underline' => 'زیرخط', 'none' => 'هیچ']],
                ['path' => 'theme.motion.text_effect', 'label' => 'افکت تیتر ویترین', 'hint' => 'انیمیشن روی تیتر اصلی ویترین.', 'control' => 'select', 'rules' => ['required', 'in:none,gradient-shift,glow-pulse'],
                    'options' => ['none' => 'بدون', 'gradient-shift' => 'گرادیان متحرک', 'glow-pulse' => 'تپش درخشش']],
                ['path' => 'theme.motion.scroll_progress', 'label' => 'نوار پیشرفت اسکرول', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.marquee_pause', 'label' => 'توقف مارکی با هاور', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.marquee_speed', 'label' => 'دور مارکی', 'hint' => 'زمان یک دور کامل نوار متحرک؛ کمتر یعنی سریع‌تر.', 'control' => 'range', 'min' => 2, 'max' => 120, 'step' => 1, 'unit' => 's', 'rules' => $dur],
                ['path' => 'theme.motion.tilt', 'label' => 'کج‌شدن سه‌بعدی کارت‌ها', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.magnetic', 'label' => 'دکمه‌های مغناطیسی', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.effects.enable_animations', 'label' => 'فعال بودن کل انیمیشن‌ها', 'hint' => 'کلید اصلی؛ خاموش بودنش همهٔ انیمیشن‌ها را از کار می‌اندازد.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.effects.hover_lift', 'label' => 'ارتفاع بلند شدن در هاور', 'control' => 'range', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.effects.animation_duration', 'label' => 'مدت انیمیشن‌های ریز', 'control' => 'range', 'min' => 50, 'max' => 1000, 'step' => 10, 'unit' => 'ms', 'rules' => $dur],
                ['path' => 'theme.landing.reveal_duration', 'label' => 'مدت نمایش هنگام اسکرول', 'control' => 'range', 'min' => 100, 'max' => 3000, 'step' => 50, 'unit' => 'ms', 'rules' => $dur],
                ['path' => 'theme.landing.reveal_offset', 'label' => 'فاصله شروع نمایش', 'control' => 'range', 'min' => 0, 'max' => 200, 'step' => 5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.stagger_ms', 'label' => 'فاصله زمانی کارت‌ها (میلی‌ثانیه)', 'control' => 'range', 'min' => 0, 'max' => 2000, 'step' => 50, 'unit' => '', 'rules' => ['required', 'integer', 'min:0', 'max:2000']],
                ['path' => 'theme.landing.float_distance', 'label' => 'دامنه شناورسازی', 'control' => 'range', 'min' => 0, 'max' => 48, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.float_duration', 'label' => 'دور شناورسازی', 'control' => 'range', 'min' => 1, 'max' => 30, 'step' => 0.5, 'unit' => 's', 'rules' => $dur],
                ['path' => 'theme.landing.counter_duration', 'label' => 'مدت شمارنده آمار (میلی‌ثانیه)', 'control' => 'range', 'min' => 0, 'max' => 20000, 'step' => 500, 'unit' => '', 'rules' => ['required', 'integer', 'min:0', 'max:20000']],
            ],
        ],

        'decoration' => [
            'label' => 'تزیینات',
            'icon'  => 'fa-shapes',
            'fields' => [
                ['path' => 'theme.decoration.section_divider', 'label' => 'جداکننده بخش‌ها', 'control' => 'select',
                    'rules' => ['required', 'in:none,line,double-line,dots,slant,curve,zigzag,gradient-band'],
                    'options' => ['none' => 'بدون', 'line' => 'خط', 'double-line' => 'دو خط', 'dots' => 'نقطه‌چین', 'slant' => 'کج',
                        'curve' => 'قوس', 'zigzag' => 'زیگزاگ', 'gradient-band' => 'نوار گرادیانی']],
                ['path' => 'theme.decoration.heading_rule', 'label' => 'خط زیر تیتر', 'control' => 'select',
                    'rules' => ['required', 'in:none,short-bar,thick-underline,full-line,number,side-rules,dot,eyebrow-pill'],
                    'options' => ['none' => 'بدون', 'short-bar' => 'میله کوتاه', 'thick-underline' => 'زیرخط ضخیم', 'full-line' => 'خط کامل',
                        'number' => 'شماره', 'side-rules' => 'خط دو طرف', 'dot' => 'نقطه', 'eyebrow-pill' => 'برچسب بیضی']],
                ['path' => 'theme.decoration.heading_align', 'label' => 'تراز تیتر بخش‌ها', 'control' => 'select', 'rules' => ['required', 'in:start,center,end,between'],
                    'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'پایان', 'between' => 'دو سر']],
                ['path' => 'theme.decoration.accent_shapes', 'label' => 'شکل‌های تزئینی', 'control' => 'select', 'rules' => ['required', 'in:none,plus,dots,grid,corner-brackets'],
                    'options' => ['none' => 'بدون', 'plus' => 'بعلاوه', 'dots' => 'نقطه‌ها', 'grid' => 'شبکه', 'corner-brackets' => 'گیوشه']],
                ['path' => 'theme.decoration.quote_mark', 'label' => 'نقل‌قول بزرگ', 'control' => 'select', 'rules' => ['required', 'in:none,serif,brand,oversized'],
                    'options' => ['none' => 'بدون', 'serif' => 'سریف', 'brand' => 'رنگ برند', 'oversized' => 'فوق‌بزرگ']],
                ['path' => 'theme.decoration.icon_backdrop', 'label' => 'زمینه آیکون‌ها', 'control' => 'select',
                    'rules' => ['required', 'in:soft-square,soft-circle,square,circle,diamond,hexagon,squircle,ring,outline,gradient,none'],
                    'options' => ['soft-square' => 'مربع نرم', 'soft-circle' => 'دایره نرم', 'square' => 'مربع', 'circle' => 'دایره',
                        'diamond' => 'لوزی', 'hexagon' => 'شش‌ضلعی', 'squircle' => 'مربع گرد', 'ring' => 'حلقه', 'outline' => 'خطی', 'gradient' => 'گرادیان', 'none' => 'بدون']],
                ['path' => 'theme.decoration.card_edge', 'label' => 'لبه کارت‌ها', 'control' => 'select', 'rules' => ['required', 'in:none,top-accent,left-accent,corner-cut,glow-border'],
                    'options' => ['none' => 'ساده', 'top-accent' => 'نوار بالا', 'left-accent' => 'نوار کنار', 'corner-cut' => 'گوشه بریده', 'glow-border' => 'حاشیه درخشان']],
            ],
        ],

        'buttons' => [
            'label' => 'دکمه‌ها',
            'icon'  => 'fa-hand-pointer',
            'fields' => [
                ['path' => 'theme.buttons.variant', 'label' => 'سبک دکمه', 'hint' => 'سبک پایهٔ همهٔ دکمه‌های سایت و پنل.', 'control' => 'select',
                    'rules' => ['required', 'in:solid,outline,soft,ghost,gradient,glass,brutal,underline'],
                    'options' => ['solid' => 'پر', 'outline' => 'خطی', 'soft' => 'نرم', 'ghost' => 'شیشه‌ای کم‌رنگ',
                        'gradient' => 'گرادیان', 'glass' => 'شیشه‌ای', 'brutal' => 'بروتال', 'underline' => 'زیرخط‌دار']],
                ['path' => 'theme.buttons.size', 'label' => 'اندازه دکمه', 'control' => 'select', 'rules' => ['required', 'in:sm,md,lg'],
                    'options' => ['sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ']],
                ['path' => 'theme.buttons.weight', 'label' => 'ضخامت متن دکمه', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.buttons.transform', 'label' => 'حروف دکمه', 'control' => 'select', 'rules' => ['required', 'in:none,uppercase,capitalize,lowercase'],
                    'options' => ['none' => 'عادی', 'uppercase' => 'بزرگ', 'capitalize' => 'سرِ حرف بزرگ', 'lowercase' => 'کوچک']],
                ['path' => 'theme.buttons.letter_spacing', 'label' => 'فاصله حروف دکمه', 'control' => 'text', 'rules' => $ls],
                ['path' => 'theme.buttons.icon', 'label' => 'آیکون پیش‌فرض دکمه', 'control' => 'select',
                    'rules' => ['required', 'in:none,arrow,arrow-ltr,arrow-down,chevron,plus,sparkle,download,play'],
                    'options' => ['none' => 'بدون', 'arrow' => 'پیکان (جهت متن)', 'arrow-ltr' => 'پیکان راست', 'arrow-down' => 'پیکان پایین',
                        'chevron' => 'شیور', 'plus' => 'بعلاوه', 'sparkle' => 'درخشش', 'download' => 'دانلود', 'play' => 'پخش']],
                ['path' => 'theme.buttons.shadow', 'label' => 'سایه دکمه', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'accessibility' => [
            'label' => 'دسترس‌پذیری',
            'icon'  => 'fa-universal-access',
            'fields' => [
                ['path' => 'theme.accessibility.contrast', 'label' => 'کنتراست', 'hint' => 'حالت «بالا» متن‌های کم‌رنگ و حاشیه‌ها را پررنگ‌تر می‌کند.', 'control' => 'select', 'rules' => ['required', 'in:normal,high'],
                    'options' => ['normal' => 'عادی', 'high' => 'بالا']],
                ['path' => 'theme.accessibility.focus_ring', 'label' => 'حالت فوکوس', 'control' => 'select', 'rules' => ['required', 'in:outline,glow,underline,none'],
                    'options' => ['outline' => 'حاشیه', 'glow' => 'درخشش', 'underline' => 'زیرخط', 'none' => 'بدون']],
                ['path' => 'theme.accessibility.reduce_motion', 'label' => 'احترام به کاهش حرکت', 'hint' => 'تعیین می‌کند اگر کاربر در دستگاهش گزینهٔ «کاهش حرکت» را فعال کرده باشد، چه رفتار کنیم.', 'control' => 'select', 'rules' => ['required', 'in:respect,force-off,force-on'],
                    'options' => ['respect' => 'رعایت تنظیم سیستم', 'force-off' => 'همیشه خاموش', 'force-on' => 'همیشه روشن']],
            ],
        ],

        'i18n' => [
            'label' => 'زمانه',
            'icon'  => 'fa-language',
            'fields' => [
                ['path' => 'theme.i18n.numerals', 'label' => 'ارقام', 'hint' => 'خودکار یعنی ارقام فارسی در متن فارسی و انگلیسی در متن لاتین.', 'control' => 'select', 'rules' => ['required', 'in:auto,fa,en'],
                    'options' => ['auto' => 'خودکار', 'fa' => 'فارسی', 'en' => 'انگلیسی']],
            ],
        ],

        'variants' => [
            'label' => 'قالب هر بخش',
            'icon'  => 'fa-puzzle-piece',
            'hint'  => 'برای هر بخش صفحهٔ اصلی چند چیدمان آماده هست؛ عوض‌کردن قالب، متن‌ها را دست نمی‌زند.',
            'fields' => [
                ['path' => 'public.landing.hero.layout', 'label' => 'قالب ویترین', 'control' => 'select', 'rules' => ['required', 'in:default,centered-stack,oversized-type'],
                    'options' => ['default' => 'دو ستونه', 'centered-stack' => 'وسط‌چین', 'oversized-type' => 'تایپوگرافی غول‌پیکر']],
                ['path' => 'public.landing.advisor.variant', 'label' => 'قالب مشاور', 'control' => 'select', 'rules' => ['required', 'in:default,quote-first'],
                    'options' => ['default' => 'کلاسیک', 'quote-first' => 'نقل‌قول‌محور']],
                ['path' => 'public.landing.ecosystem.variant', 'label' => 'قالب اکوسیستم', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
                ['path' => 'public.landing.services.variant', 'label' => 'قالب خدمات', 'control' => 'select', 'rules' => ['required', 'in:default,numbered-list,bento'],
                    'options' => ['default' => 'کارتی', 'numbered-list' => 'لیست شماره‌دار', 'bento' => 'بنتو']],
                ['path' => 'public.landing.stats.variant', 'label' => 'قالب آمار', 'control' => 'select', 'rules' => ['required', 'in:default,inline-divider,band,boxed'],
                    'options' => ['default' => 'پیش‌فرض', 'inline-divider' => 'با خط جدا', 'band' => 'نوار تمام‌عرض', 'boxed' => 'کادرسازی']],
                ['path' => 'public.landing.testimonials.variant', 'label' => 'قالب نظرات', 'control' => 'select', 'rules' => ['required', 'in:default,single-featured,marquee,masonry'],
                    'options' => ['default' => 'شبکه‌ای', 'single-featured' => 'یک نظر برجسته', 'marquee' => 'متحرک', 'masonry' => 'آجری']],
                ['path' => 'public.landing.blog.variant', 'label' => 'قالب وبلاگ', 'control' => 'select', 'rules' => ['required', 'in:default,list'],
                    'options' => ['default' => 'کارتی', 'list' => 'لیستی']],
                ['path' => 'public.landing.cta.variant', 'label' => 'قالب دعوت به اقدام', 'control' => 'select', 'rules' => ['required', 'in:default,boxed-card,split'],
                    'options' => ['default' => 'پیش‌فرض', 'boxed-card' => 'کارت جعبه‌ای', 'split' => 'دو ستونه']],
                ['path' => 'public.landing.logos.variant', 'label' => 'قالب لوگوها', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
                ['path' => 'public.landing.process.variant', 'label' => 'قالب فرآیند', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
                ['path' => 'public.landing.faq.variant', 'label' => 'قالب پرسش‌ها', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
                ['path' => 'public.landing.comparison.variant', 'label' => 'قالب مقایسه', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
                ['path' => 'public.landing.blocks.variant', 'label' => 'قالب بلوک‌ها', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'پیش‌فرض']],
            ],
        ],

        'landing' => [
            'label' => 'ساختار صفحه اصلی',
            'icon'  => 'fa-sitemap',
            'fields' => [
                ['path' => 'public.landing.sections', 'label' => 'بخش‌های صفحه اصلی', 'hint' => 'ترتیب و حضور بخش‌ها در صفحهٔ اصلی. ویترین قفل است: همیشه اول و همیشه نمایش داده می‌شود.', 'control' => 'sections', 'rules' => ['required', 'array', 'min:1'],
                    'locked' => ['hero'],
                    'options' => [
                        'hero' => 'ویترین', 'advisor' => 'مشاور', 'ecosystem' => 'اکوسیستم', 'services' => 'خدمات',
                        'stats' => 'آمار', 'testimonials' => 'نظرات', 'blog' => 'وبلاگ', 'cta' => 'دعوت به اقدام',
                        'logos' => 'لوگوها', 'process' => 'فرآیند', 'faq' => 'پرسش‌ها', 'comparison' => 'مقایسه',
                        'blocks' => 'بلوک‌های سفارشی',
                    ]],
                ['path' => 'public.landing.meta.title', 'label' => 'عنوان متا (SEO)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.meta.description', 'label' => 'توضیحات متا', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.meta.og_image', 'label' => 'تصویر اشتراک‌گذاری (og:image)', 'hint' => 'تصویری که هنگام اشتراک‌گذاری لینک در شبکه‌های اجتماعی دیده می‌شود.', 'control' => 'image', 'folder' => 'meta', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']],
                ['path' => 'public.landing.animations.reveal', 'label' => 'انیمیشن نمایش هنگام اسکرول', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.animations.float', 'label' => 'شناورسازی کارت‌های ویترین', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.animations.counters', 'label' => 'شمارنده زنده آمار', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.blog.source', 'label' => 'منبع بخش وبلاگ', 'hint' => '«نوشته‌های واقعی» همان مقالات بخش مدیریت وبلاگ هستند.', 'control' => 'select', 'rules' => ['required', 'in:config,database'],
                    'options' => ['config' => 'محتوای پیکربندی', 'database' => 'نوشته‌های واقعی']],
                ['path' => 'public.landing.blog.count', 'label' => 'تعداد مقالات صفحه اصلی', 'control' => 'range', 'min' => 1, 'max' => 12, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:12']],
                ['path' => 'public.landing.services.columns', 'label' => 'ستون‌های خدمات', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.stats.columns', 'label' => 'ستون‌های آمار', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.testimonials.columns', 'label' => 'ستون‌های نظرات (خالی = خودکار)', 'hint' => 'خالی گذاشتن یعنی خودکار بر اساس قالب انتخاب‌شده.', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.blog.columns', 'label' => 'ستون‌های وبلاگ', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
            ],
        ],

        'hero' => [
            'label' => 'متن ویترین',
            'icon'  => 'fa-bullhorn',
            'fields' => [
                ['path' => 'public.landing.hero.title_line1', 'label' => 'سطر اول تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.hero.title_line2', 'label' => 'سطر دوم تیتر (برند)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.hero.subtitle', 'label' => 'زیرتیتر', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.hero.eyebrow', 'label' => 'برچسب بالای تیتر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.hero.gradient_text', 'label' => 'گرادیان روی سطر دوم', 'hint' => 'سطر دوم تیتر با گرادیان رنگ‌های برند نوشته می‌شود.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.hero.gradient_dir', 'label' => 'جهت گرادیان تیتر', 'control' => 'select', 'rules' => ['required', 'in:to-l,to-r'],
                    'options' => ['to-l' => 'به چپ', 'to-r' => 'به راست']],
                ['path' => 'public.landing.hero.text_align', 'label' => 'تراز متن ویترین', 'control' => 'select', 'rules' => ['required', 'in:start,center,end'],
                    'options' => ['start' => 'ابتدا', 'center' => 'وسط', 'end' => 'پایان']],
                ['path' => 'public.landing.hero.text_side', 'label' => 'سمت ستون متن', 'hint' => 'ستون متن در ابتدای صفحه (راست) باشد یا انتها (چپ).', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'ابتدا', 'end' => 'پایان']],
                ['path' => 'public.landing.hero.mockup', 'label' => 'نمایش ماکت داشبورد', 'hint' => 'نمایش تصویر ساختگیِ داشبورد کنار تیتر ویترین.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.hero.media', 'label' => 'رسانه ستون دوم', 'hint' => 'مشخص می‌کند ستون دوم ویترین چه چیزی نشان دهد.', 'control' => 'select', 'rules' => ['required', 'in:mockup,photo,none'],
                    'options' => ['mockup' => 'ماکت', 'photo' => 'تصویر اختصاصی', 'none' => 'بدون']],
                ['path' => 'public.landing.hero.image', 'label' => 'تصویر ویترین', 'control' => 'image', 'folder' => 'hero', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'public.landing.hero.image_alt', 'label' => 'متن جایگزین تصویر ویترین', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.hero.buttons', 'label' => 'دکمه‌های ویترین', 'hint' => 'هر ردیف یک دکمه است؛ با «نمایش» می‌توان موقتاً پنهانش کرد. خالی‌کردن کامل فهرست، دکمه‌های پیش‌فرض فایل پایه را بازمی‌گرداند.', 'control' => 'list', 'max' => 4, 'rules' => ['nullable', 'array', 'max:4'],
                    'item' => [
                        ['key' => 'label', 'label' => 'متن', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'نشانی', 'control' => 'text', 'rules' => $href],
                        ['key' => 'style', 'label' => 'سبک', 'control' => 'select', 'rules' => ['required', 'in:solid,ghost,primary,outline'], 'options' => $btnStyle],
                        ['key' => 'icon', 'label' => 'آیکون', 'control' => 'select', 'rules' => ['nullable', 'string', 'max:20'], 'options' => $btnIcon],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'advisor' => [
            'label' => 'متن مشاور',
            'icon'  => 'fa-user-tie',
            'fields' => [
                ['path' => 'public.landing.advisor.name', 'label' => 'نام مشاور', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.tagline', 'label' => 'شعار مشاور', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.tagline_color', 'label' => 'رنگ شعار', 'control' => 'select',
                    'rules' => ['required', 'in:primary,secondary,accent_blue,accent_emerald,accent_orange,accent_teal,accent_red,accent_violet,accent_pink,accent_lime,accent_cyan,accent_amber,accent_rose'],
                    'options' => ['primary' => 'رنگ اصلی', 'secondary' => 'رنگ ثانویه', 'accent_blue' => 'تأکید آبی', 'accent_emerald' => 'تأکید زمردی',
                        'accent_orange' => 'تأکید نارنجی', 'accent_teal' => 'تأکید فیروزه‌ای', 'accent_red' => 'تأکید قرمز', 'accent_violet' => 'تأکید بنفش',
                        'accent_pink' => 'تأکید صورتی', 'accent_lime' => 'تأکید لیمویی', 'accent_cyan' => 'تأکید آبی آسمانی', 'accent_amber' => 'تأکید کهربایی',
                        'accent_rose' => 'تأکید گل‌سرخی']],
                ['path' => 'public.landing.advisor.bio', 'label' => 'بیوگرافی', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                ['path' => 'public.landing.advisor.eyebrow.label', 'label' => 'برچسب بالای بخش', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.eyebrow.icon', 'label' => 'آیکون کلاس (Font Awesome)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/']],
                ['path' => 'public.landing.advisor.badge.label', 'label' => 'برچسب نشان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.badge.visible', 'label' => 'نمایش نشان', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.image', 'label' => 'تصویر مشاور', 'control' => 'image', 'folder' => 'advisor', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'public.landing.advisor.image_alt', 'label' => 'متن جایگزین تصویر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.image_side', 'label' => 'سمت تصویر', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'ابتدا', 'end' => 'پایان']],
                ['path' => 'public.landing.advisor.image_size', 'label' => 'اندازه تصویر', 'control' => 'range', 'min' => 8, 'max' => 40, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.landing.advisor.spin_rings', 'label' => 'حلقه‌های چرخان دور تصویر', 'hint' => 'حلقه‌های تزئینی چرخان دور تصویر مشاور.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.grayscale', 'label' => 'سیاه‌وسفید بودن تصویر', 'hint' => 'تصویر مشاور سیاه‌وسفید نمایش داده می‌شود.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.buttons', 'label' => 'دکمه‌های مشاور', 'control' => 'list', 'max' => 3, 'rules' => ['nullable', 'array', 'max:3'],
                    'item' => [
                        ['key' => 'label', 'label' => 'متن', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'نشانی', 'control' => 'text', 'rules' => $href],
                        ['key' => 'style', 'label' => 'سبک', 'control' => 'select', 'rules' => ['required', 'in:primary,ghost,solid,outline'], 'options' => $btnStyle],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'sections_content' => [
            'label' => 'متن بخش‌ها',
            'icon'  => 'fa-align-left',
            'fields' => [
                ['path' => 'public.landing.ecosystem.heading', 'label' => 'اکوسیستم: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.ecosystem.text', 'label' => 'اکوسیستم: متن', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                ['path' => 'public.landing.ecosystem.text_side', 'label' => 'اکوسیستم: سمت متن', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'ابتدا', 'end' => 'پایان']],
                ['path' => 'public.landing.ecosystem.visual', 'label' => 'اکوسیستم: پنل گرافیکی', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.services.heading', 'label' => 'خدمات: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.services.subheading', 'label' => 'خدمات: زیرتیتر', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.testimonials.heading', 'label' => 'نظرات: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.testimonials.subheading', 'label' => 'نظرات: زیرتیتر', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.blog.heading', 'label' => 'وبلاگ: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.blog.subheading', 'label' => 'وبلاگ: زیرتیتر', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.blog.see_all.label', 'label' => 'وبلاگ: متن «مشاهده همه»', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.blog.see_all.visible', 'label' => 'وبلاگ: نمایش لینک «مشاهده همه»', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.cta.heading', 'label' => 'دعوت به اقدام: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.cta.text', 'label' => 'دعوت به اقدام: متن', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.logos.heading', 'label' => 'لوگوها: تیتر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.process.heading', 'label' => 'فرآیند: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.process.subheading', 'label' => 'فرآیند: زیرتیتر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.faq.heading', 'label' => 'پرسش‌ها: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.faq.subheading', 'label' => 'پرسش‌ها: زیرتیتر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.comparison.heading', 'label' => 'مقایسه: تیتر', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.comparison.subheading', 'label' => 'مقایسه: زیرتیتر', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.services.items', 'label' => 'کارت‌های خدمات', 'hint' => 'افزودن، حذف و ترتیب کارت‌ها؛ «نمایش» هر کارت را یکی‌یکی خاموش/روشن می‌کند.', 'control' => 'list', 'max' => 12, 'rules' => ['nullable', 'array', 'max:12'],
                    'item' => [
                        ['key' => 'title', 'label' => 'عنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'توضیح', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'icon', 'label' => 'آیکون (Font Awesome)', 'control' => 'text', 'rules' => $faClass],
                        ['key' => 'accent', 'label' => 'رنگ', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.ecosystem.items', 'label' => 'موارد اکوسیستم', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'متن', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'icon', 'label' => 'نماد', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:8']],
                        ['key' => 'accent', 'label' => 'رنگ', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.stats.items', 'label' => 'شمارنده‌های آمار', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'برچسب', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'value', 'label' => 'مقدار', 'control' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:999999999']],
                        ['key' => 'suffix', 'label' => 'پسوند', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:10']],
                        ['key' => 'gradient', 'label' => 'گرادیان', 'control' => 'toggle'],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.testimonials.items', 'label' => 'نقل‌قول‌های نظرات', 'control' => 'list', 'max' => 12, 'rules' => ['nullable', 'array', 'max:12'],
                    'item' => [
                        ['key' => 'name', 'label' => 'نام', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'result', 'label' => 'نتیجه', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'initials', 'label' => 'حروف اول', 'control' => 'text', 'rules' => ['required', 'string', 'max:10']],
                        ['key' => 'text', 'label' => 'متن نقل‌قول', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'from', 'label' => 'گرادیان از', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'to', 'label' => 'گرادیان به', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.faq.items', 'label' => 'پرسش‌های متداول', 'control' => 'list', 'max' => 20, 'rules' => ['nullable', 'array', 'max:20'],
                    'item' => [
                        ['key' => 'question', 'label' => 'پرسش', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'answer', 'label' => 'پاسخ', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.process.items', 'label' => 'مراحل فرآیند', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'title', 'label' => 'عنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'توضیح', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.cta.buttons', 'label' => 'دکمه‌های دعوت به اقدام', 'control' => 'list', 'max' => 3, 'rules' => ['nullable', 'array', 'max:3'],
                    'item' => [
                        ['key' => 'label', 'label' => 'متن', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'route', 'label' => 'مقصد', 'control' => 'select', 'rules' => ['required', 'in:'.implode(',', array_keys($siteRoutes))], 'options' => $siteRoutes],
                        ['key' => 'style', 'label' => 'سبک', 'control' => 'select', 'rules' => ['required', 'in:solid,ghost,primary,outline'], 'options' => $btnStyle],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'blocks' => [
            'label' => 'بلوک‌های سفارشی',
            'icon'  => 'fa-cubes',
            'hint'  => 'یک بخش آزاد که خودتان از تیتر، متن، دکمه، کارت، تصویر و فاصله می‌چینید. نوع هر ردیف مشخص می‌کند چه فیلدهایی داشته باشد؛ سپس بخش را از «ساختار صفحه اصلی» به صفحه اضافه کنید.',
            'fields' => [
                ['path' => 'public.landing.blocks.items', 'label' => 'بلوک‌ها', 'hint' => 'هر ردیف یک بلوک است؛ با «نمایش» می‌توان موقتاً پنهانش کرد. خالی‌کردن کامل فهرست، هیچ بلوکی در صفحه نشان نمی‌دهد.', 'control' => 'list', 'max' => 24, 'rules' => ['nullable', 'array', 'max:24'],
                    'item' => [
                        ['key' => 'type', 'label' => 'نوع بلوک', 'control' => 'select', 'discriminant' => true,
                            'rules' => ['required', 'in:'.implode(',', array_keys($blockTypes))], 'options' => $blockTypes],
                        ['key' => 'title', 'label' => 'عنوان / متن دکمه', 'control' => 'text', 'show_for' => ['heading', 'card', 'button'], 'required_for' => ['heading', 'card', 'button'],
                            'rules' => ['nullable', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'متن', 'control' => 'textarea', 'show_for' => ['heading', 'text', 'card', 'image'], 'required_for' => ['text'],
                            'rules' => ['nullable', 'string', 'max:4000']],
                        ['key' => 'href', 'label' => 'نشانی', 'control' => 'text', 'show_for' => ['button'], 'required_for' => ['button'],
                            'rules' => $hrefOpt],
                        ['key' => 'style', 'label' => 'سبک', 'control' => 'select', 'show_for' => ['button'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($btnStyleOpt))], 'options' => $btnStyleOpt],
                        ['key' => 'icon', 'label' => 'آیکون', 'control' => 'select', 'show_for' => ['button'],
                            'rules' => ['nullable', 'string', 'max:20', 'in:'.implode(',', array_keys($btnIcon))], 'options' => $btnIcon],
                        ['key' => 'fa_icon', 'label' => 'آیکون کارت (Font Awesome)', 'control' => 'text', 'show_for' => ['card'],
                            'rules' => $faClass],
                        ['key' => 'accent', 'label' => 'رنگ تأکید', 'control' => 'select', 'show_for' => ['card'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($accentsOpt))], 'options' => $accentsOpt],
                        ['key' => 'align', 'label' => 'تراز', 'control' => 'select', 'show_for' => ['heading', 'text'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($alignOpt))], 'options' => $alignOpt],
                        ['key' => 'src', 'label' => 'نشانی تصویر', 'control' => 'text', 'show_for' => ['image'], 'required_for' => ['image'],
                            'rules' => $imgSrc],
                        ['key' => 'size', 'label' => 'اندازه فاصله', 'control' => 'select', 'show_for' => ['spacer'],
                            'rules' => ['nullable', 'in:,sm,md,lg'],
                            'options' => ['' => 'متوسط', 'sm' => 'کم', 'md' => 'متوسط', 'lg' => 'زیاد']],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'nav' => [
            'label' => 'منوی سایت',
            'icon'  => 'fa-bars',
            'fields' => [
                ['path' => 'public.nav.enabled', 'label' => 'نمایش منو', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.style', 'label' => 'جنس منو', 'hint' => 'شیشه‌ای پشت منو را محو می‌کند؛ شفاف هیچ زمینه‌ای ندارد.', 'control' => 'select', 'rules' => ['required', 'in:solid,glass,transparent'],
                    'options' => ['solid' => 'مات', 'glass' => 'شیشه‌ای', 'transparent' => 'شفاف']],
                ['path' => 'public.nav.sticky', 'label' => 'چسبان بودن منو', 'hint' => 'منو هنگام اسکرول در بالای صفحه می‌ماند.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.show_logo_mark', 'label' => 'نمایش نشان در منو', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.links_style', 'label' => 'هاور لینک‌های منو', 'control' => 'select', 'rules' => ['required', 'in:plain,underline,pill'],
                    'options' => ['plain' => 'ساده', 'underline' => 'زیرخط', 'pill' => 'قرصی']],
                ['path' => 'public.nav.cta_style', 'label' => 'سبک دکمه ورود', 'control' => 'select', 'rules' => ['required', 'in:glass,solid,outline,gradient'],
                    'options' => ['glass' => 'شیشه‌ای', 'solid' => 'پر', 'outline' => 'خطی', 'gradient' => 'گرادیان']],
                ['path' => 'public.nav.cta.label', 'label' => 'متن دکمه ورود', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.nav.cta.visible', 'label' => 'نمایش دکمه ورود', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.links', 'label' => 'لینک‌های منو', 'hint' => 'لینک‌های صفحهٔ اصلی معمولاً لنگر هستند؛ نشانی با #، / یا https معتبر است.', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'متن', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'نشانی', 'control' => 'text', 'rules' => $href],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'footer' => [
            'label' => 'پاورقی',
            'icon'  => 'fa-shoe-prints',
            'fields' => [
                ['path' => 'public.footer.enabled', 'label' => 'نمایش پاورقی', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.footer.show_logo_mark', 'label' => 'نمایش نشان', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.footer.variant', 'label' => 'ساختار پاورقی', 'control' => 'select', 'rules' => ['required', 'in:columns,centered,minimal'],
                    'options' => ['columns' => 'چند ستونه', 'centered' => 'وسط‌چین', 'minimal' => 'حداقلی']],
                ['path' => 'public.footer.background', 'label' => 'پس‌زمینه پاورقی', 'control' => 'select', 'rules' => ['required', 'in:background,surface,gradient'],
                    'options' => ['background' => 'پس‌زمینه سایت', 'surface' => 'سطح', 'gradient' => 'گرادیان برند']],
                ['path' => 'public.footer.blurb', 'label' => 'متن برند', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.footer.copyright', 'label' => 'متن کپی‌رایت (:name و :year مجاز)', 'hint' => '«:name» با نام مجموعه و «:year» با سال جاری جایگزین می‌شود.', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.footer.social', 'label' => 'شبکه‌های اجتماعی', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'نام', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'url', 'label' => 'نشانی', 'control' => 'text', 'rules' => $href],
                        ['key' => 'icon', 'label' => 'آیکون (Font Awesome)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/']],
                        ['key' => 'visible', 'label' => 'نمایش', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'login' => [
            'label' => 'صفحه ورود',
            'icon'  => 'fa-right-to-bracket',
            'fields' => [
                ['path' => 'public.login.title', 'label' => 'عنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.subtitle', 'label' => 'زیرعنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.email_label', 'label' => 'برچسب ایمیل', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.password_label', 'label' => 'برچسب رمز عبور', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.remember_label', 'label' => 'برچسب «مرا به خاطر بسپار»', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.submit_label', 'label' => 'متن دکمه ورود', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.back_link.label', 'label' => 'متن لینک بازگشت', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.back_link.visible', 'label' => 'نمایش لینک بازگشت', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.login.card.max_width', 'label' => 'عرض کارت ورود', 'control' => 'range', 'min' => 12, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.login.card.glass', 'label' => 'شیشه‌ای بودن کارت', 'hint' => 'کارت ورود شیشه‌ای می‌شود و پشتش محو دیده می‌شود.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.student_login.title', 'label' => 'ورود دانش‌آموز: عنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.subtitle', 'label' => 'ورود دانش‌آموز: زیرعنوان', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.submit_label', 'label' => 'ورود دانش‌آموز: دکمه', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.card.max_width', 'label' => 'ورود دانش‌آموز: عرض کارت', 'control' => 'range', 'min' => 12, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.student_login.card.glass', 'label' => 'ورود دانش‌آموز: شیشه‌ای بودن', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'features' => [
            'label' => 'ویژگی‌ها',
            'icon'  => 'fa-toggle-on',
            'hint'  => 'با خاموش‌کردن هر کلید، آن قابلیت از کل پنل این مجموعه حذف می‌شود.',
            'admin' => true,
            'fields' => [
                ['path' => 'features.dashboard', 'label' => 'داشبورد', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.blog_management', 'label' => 'مدیریت وبلاگ', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.direct_chat', 'label' => 'گفتگوی مستقیم', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.theme_studio', 'label' => 'استودیوی ظاهر', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.bulk_actions', 'label' => 'اقدامات گروهی', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.settings_chat', 'label' => 'تنظیمات گفتگو', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.settings_profile', 'label' => 'تنظیمات پروفایل', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.appearance_staff_publish', 'label' => 'اجازه انتشار ظاهر به کارکنان', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_profile', 'label' => 'پروفایل دانش‌آموز', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.report_cards', 'label' => 'کارنامه‌ها', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_exams', 'label' => 'آزمون‌ها', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_schedule', 'label' => 'برنامه هفتگی', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.quiz_management', 'label' => 'مدیریت آزمونک', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.question_management', 'label' => 'مدیریت سوالات', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.book_access', 'label' => 'دسترسی کتاب', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.create_post_action', 'label' => 'دکمه ایجاد پست', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
            ],
        ],
    ],
];
