<?php

use App\Support\ThemeIcons;

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
|   control  color | text | textarea | select | toggle | number | range | font | image | sections | archetype | list | canvas | hidden
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
|            `content` => false marks metadata that cannot keep an otherwise
|            abandoned row alive (identity, styles, or spacer size).
|   max      list control: the most rows a submission may carry
|   canvas   control: one map of element id => {props, states, breakpoints,
|            flags}. The whole map is validated by StudioStyles::cleanNodes,
|            which drops unknown ids, unknown keys, disabled states under a
|            hidden node, and any value whose shape is not exactly known.
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
$hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
$len = ['required', 'string', 'max:20', 'regex:/^[0-9.]+(px|rem|em|%)$/'];
$lenView = ['required', 'string', 'max:20', 'regex:/^[0-9.]+(px|rem|em|%|svh|vh|dvh)$/'];
$num = ['required', 'string', 'max:10', 'regex:/^[0-9.]+$/'];
$dur = ['required', 'string', 'max:10', 'regex:/^[0-9.]+(ms|s)$/'];
$ls = ['required', 'string', 'max:20', 'regex:/^-?[0-9.]+(em|px|rem)?$/'];
$lh = ['required', 'string', 'max:20', 'regex:/^([0-9.]+|inherit|normal)$/'];
$clamp = ['required', 'string', 'max:60', 'regex:/^[0-9a-zA-Z(),.\s%+-]+$/'];
// Directory membership is resolved by StudioSchema at runtime, not config-cache time.
$font = ['nullable', 'string'];
$bool = ['nullable', 'boolean'];

$weights = [
    'options' => ['300' => '300', '400' => '400', '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900'],
    'in' => 'in:300,400,500,600,700,800,900',
];

// Shared option lists and rule fragments for the list control's item defs.
// Every select here stays a whitelist: its value reaches a CSS class name or
// a route() call, so free text is never acceptable.
$accents = [
    'primary' => 'Primary color',
    'secondary' => 'Secondary color',
    'accent_blue' => 'Accent blue',
    'accent_emerald' => 'Accent emerald',
    'accent_orange' => 'Accent orange',
    'accent_teal' => 'Accent teal',
    'accent_red' => 'Accent red',
    'accent_violet' => 'Accent violet',
    'accent_pink' => 'Accent pink',
    'accent_lime' => 'Accent lime',
    'accent_cyan' => 'Accent sky blue',
    'accent_amber' => 'Accent amber',
    'accent_rose' => 'Accent rose',
];
$accentsIn = 'in:'.implode(',', array_keys($accents));
$href = ['required', 'string', 'max:2048', 'regex:/^(#|\/|https?:\/\/)[A-Za-z0-9 ._\/?%&#=:;\-]*$/'];
$faClass = ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/'];
$btnStyle = ['solid' => 'Filled', 'ghost' => 'Subtle glass', 'primary' => 'Brand', 'outline' => 'Outline'];
$btnIcon = ['' => 'Theme default', 'none' => 'None', 'arrow' => 'Arrow (follows text)', 'arrow-ltr' => 'Arrow right', 'arrow-down' => 'Arrow down', 'chevron' => 'Chevron', 'plus' => 'Plus', 'sparkle' => 'Glow', 'download' => 'Download', 'play' => 'Play'];
$siteRoutes = ['login' => 'Login page', 'home' => 'Home page', 'about' => 'About us', 'contact' => 'Contact', 'student.login' => 'Student login'];

// Typed-block variants of the shared fragments. The leading '' option on
// every block select matters twice over: it keeps an untouched select
// diff-free against a baseline row that omits the key (the radio-chip
// contract always submits something), and normalizeList drops '' so the
// renderer falls back to the theme default.
$hrefOpt = array_merge(['nullable'], array_slice($href, 1));
$btnStyleOpt = ['' => 'Theme default'] + $btnStyle;
$accentsOpt = ['' => 'Default'] + $accents;
$alignOpt = ['' => 'Auto', 'start' => 'Start', 'center' => 'Center', 'end' => 'End'];
$imgSrc = ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)[A-Za-z0-9 ._\/?%&#=-]+$/'];
$blockTypes = [
    'heading' => 'Heading', 'text' => 'Text', 'button' => 'Button', 'card' => 'Card',
    'image' => 'Image', 'spacer' => 'Spacer', 'divider' => 'Divider',
];

return [

    'groups' => [

        'brand' => [
            'label' => 'Brand',
            'icon' => 'fa-fingerprint',
            'fields' => [
                ['path' => 'theme.archetype', 'label' => 'Archetype (ready-made visual identity)', 'hint' => 'Picks color, font and surface material from a ready-made identity; your manual changes stack on top of it.', 'control' => 'archetype', 'rules' => ['required', 'string']],
                ['path' => 'tenant.name', 'label' => 'Brand name', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.short_name', 'label' => 'Short name', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.page_title', 'label' => 'Page title', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'tenant.role_label', 'label' => 'Panel label in the top bar', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'tenant.favicon', 'label' => 'Favicon (URL)', 'hint' => 'The small browser-tab icon; a /storage/… path or a full https:// address.', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)[A-Za-z0-9 ._\/?%&#=-]+$/']],
                ['path' => 'theme.brand.src', 'label' => 'Logo image', 'control' => 'image', 'folder' => 'brand', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'theme.brand.variant', 'label' => 'Brand lockup variant', 'hint' => 'How the mark and name are arranged in the site and panel header.', 'control' => 'select', 'rules' => ['nullable', 'in:mark,wordmark,mark+word,stacked'],
                    'options' => ['mark' => 'Mark only', 'wordmark' => 'Text only', 'mark+word' => 'Mark + text', 'stacked' => 'Stacked']],
                ['path' => 'theme.brand.height', 'label' => 'Logo height', 'hint' => 'Logo size; width and spacing adapt automatically.', 'control' => 'range', 'min' => 1, 'max' => 8, 'step' => 0.25, 'unit' => 'rem', 'rules' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+(rem|px|em)$/']],
            ],
        ],

        'colors' => [
            'label' => 'Colors',
            'icon' => 'fa-palette',
            'fields' => [
                ['path' => 'theme.colors.primary', 'label' => 'Primary color', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.secondary', 'label' => 'Secondary color', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.background', 'label' => 'Background', 'hint' => 'The base background of every page.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.surface', 'label' => 'Surface', 'hint' => 'The color of cards, panels and forms.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.surface_alt', 'label' => 'Alternate surface', 'hint' => 'The second background; for alternating sections and secondary surfaces.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.text', 'label' => 'Text', 'hint' => 'Main text color; keep enough contrast with “Background”.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.heading', 'label' => 'Headings', 'hint' => 'Heading color; use the same value as text if you want them identical.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.link', 'label' => 'Links', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.success', 'label' => 'Success', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.info', 'label' => 'Info', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.warning', 'label' => 'Warning', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.danger', 'label' => 'Danger', 'control' => 'color', 'rules' => $hex],

                // The accent palette is content-addressable: any item in the
                // section configs can name these (accent => 'accent-violet').
                ['path' => 'theme.colors.accent_blue', 'label' => 'Accent blue', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_emerald', 'label' => 'Accent emerald', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_orange', 'label' => 'Accent orange', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_teal', 'label' => 'Accent teal', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_red', 'label' => 'Accent red', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_violet', 'label' => 'Accent violet', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_pink', 'label' => 'Accent pink', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_lime', 'label' => 'Accent lime', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_cyan', 'label' => 'Accent sky blue', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_amber', 'label' => 'Accent amber', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.colors.accent_rose', 'label' => 'Accent rose', 'control' => 'color', 'rules' => $hex],

                // The light colour-scheme the visitor toggle can switch to.
                ['path' => 'theme.schemes.light.background', 'label' => 'Light: background', 'hint' => 'The light mode is what visitors see with the theme toggle; the rest of this mode’s colors are derived automatically.', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface', 'label' => 'Light: surface', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface_alt', 'label' => 'Light: alternate surface', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.surface_elevated', 'label' => 'Light: elevated surface', 'control' => 'color', 'rules' => $hex],
                ['path' => 'theme.schemes.light.text', 'label' => 'Light: text', 'control' => 'color', 'rules' => $hex],
            ],
        ],

        'typography' => [
            'label' => 'Typography',
            'icon' => 'fa-font',
            'fields' => [
                ['path' => 'theme.typography.font_family', 'label' => 'Body font', 'hint' => 'A font folder installed under public/fonts; keeping the current value or resetting is also possible.', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_heading', 'label' => 'Heading font', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_accent', 'label' => 'Accent font', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_button', 'label' => 'Button font', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.font_mono', 'label' => 'Monospace font', 'control' => 'font', 'rules' => $font],
                ['path' => 'theme.typography.body_size', 'label' => 'Body size', 'hint' => 'Base text size; headings have their own sizes.', 'control' => 'range', 'min' => 10, 'max' => 28, 'step' => 0.5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.typography.body_weight', 'label' => 'Body weight', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.typography.heading_weight', 'label' => 'Heading weight', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.typography.heading_transform', 'label' => 'Heading case', 'control' => 'select',
                    'rules' => ['required', 'in:none,uppercase,capitalize,lowercase'],
                    'options' => ['none' => 'Normal', 'uppercase' => 'Uppercase', 'capitalize' => 'Capitalize', 'lowercase' => 'Lowercase']],
                ['path' => 'theme.typography.heading_letter_spacing', 'label' => 'Heading letter spacing', 'control' => 'range', 'min' => -0.1, 'max' => 0.2, 'step' => 0.005, 'unit' => 'em', 'rules' => $ls],
                ['path' => 'theme.typography.heading_line_height', 'label' => 'Heading line height', 'control' => 'range', 'min' => 0.8, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.heading_balance', 'label' => 'Heading line balance', 'hint' => 'Tries to keep heading lines about the same length; browser support varies.', 'control' => 'select',
                    'rules' => ['required', 'in:auto,balance,pretty'],
                    'options' => ['auto' => 'Auto', 'balance' => 'Balanced', 'pretty' => 'Pretty']],
                ['path' => 'theme.typography.h1_size', 'label' => 'H1 size', 'hint' => 'A clamp value means “minimum, scales with viewport, maximum”.', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.h2_size', 'label' => 'H2 size', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.h3_size', 'label' => 'H3 size', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.stat_size', 'label' => 'Stat number size', 'control' => 'text', 'rules' => $clamp],
                ['path' => 'theme.typography.hero_line_height', 'label' => 'Hero heading line height', 'control' => 'range', 'min' => 0.8, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.letter_spacing', 'label' => 'Body letter spacing', 'hint' => 'Negative values pull letters together; positive values open them up.', 'control' => 'range', 'min' => -0.05, 'max' => 0.2, 'step' => 0.005, 'unit' => '', 'rules' => $ls],
                ['path' => 'theme.typography.line_height', 'label' => 'Body line height', 'control' => 'range', 'min' => 1, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $lh],
                ['path' => 'theme.typography.measure', 'label' => 'Ideal paragraph width', 'hint' => 'Maximum paragraph width; narrower is easier to read.', 'control' => 'range', 'min' => 20, 'max' => 100, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
            ],
        ],

        'icons' => [
            'label' => 'Icons',
            'icon' => 'fa-shapes',
            'fields' => [
                ['path' => 'theme.icons.set', 'label' => 'Icon set', 'hint' => 'The style of every icon on the site and panel; each option previews the real icon shapes.', 'control' => 'iconset', 'rules' => ['required', 'in:'.implode(',', array_keys(ThemeIcons::choices()))]],
            ],
        ],

        'surface' => [
            'label' => 'Surface & depth',
            'icon' => 'fa-layer-group',
            'fields' => [
                ['path' => 'theme.surface.material', 'label' => 'Surface material', 'hint' => 'The material of cards and panels; “Glass” blurs what is behind a card.', 'control' => 'select', 'rules' => ['nullable', 'in:glass,acrylic,matte,paper,flat'],
                    'options' => ['glass' => 'Glass', 'acrylic' => 'Acrylic', 'matte' => 'Matte', 'paper' => 'Paper', 'flat' => 'Flat']],
                ['path' => 'theme.surface.blur', 'label' => 'Glass blur', 'hint' => 'How much is blurred under glass and acrylic surfaces.', 'control' => 'range', 'min' => 0, 'max' => 40, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.surface.opacity', 'label' => 'Surface opacity (0 to 1)', 'hint' => 'How much light passes through cards; 1 means fully matte.', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.surface.noise', 'label' => 'Surface grain (0 to 1)', 'hint' => 'A fine grain texture on the surface, for a paper feel.', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.surface.specular', 'label' => 'Surface light reflection', 'control' => 'text', 'rules' => ['required', 'string', 'max:80', 'regex:/^[0-9a-zA-Z(),.\s%+-]+$/']],
                ['path' => 'theme.surface.inner_border', 'label' => 'Surface inner rim', 'control' => 'range', 'min' => 0, 'max' => 8, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.depth.elevation', 'label' => 'Elevation model', 'hint' => 'Shadow style; “neon” glows brighter on dark themes.', 'control' => 'select', 'rules' => ['required', 'in:flat,ambient,soft,medium,heavy,neon,brutal'],
                    'options' => ['flat' => 'Flat', 'ambient' => 'Ambient', 'soft' => 'Soft', 'medium' => 'Medium', 'heavy' => 'Heavy', 'neon' => 'Neon', 'brutal' => 'Brutal']],
                ['path' => 'theme.depth.shadow_tint', 'label' => 'Tinted shadow (brand)', 'hint' => 'Shadows pick up the brand tint instead of grey.', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'shape' => [
            'label' => 'Radius & lines',
            'icon' => 'fa-vector-square',
            'fields' => [
                ['path' => 'theme.shape.radius_scale', 'label' => 'Radius scale (all at once)', 'hint' => 'Fills every unset radius with one ready-made scale; values you set by hand are kept.', 'control' => 'select', 'rules' => ['nullable', 'in:auto,sharp,slight,soft,round,pill'],
                    'options' => ['auto' => 'Auto', 'sharp' => 'Sharp', 'slight' => 'Slight', 'soft' => 'Soft', 'round' => 'Round', 'pill' => 'Pill']],
                ['path' => 'theme.shape.radius_sm', 'label' => 'Small radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.radius_md', 'label' => 'Medium radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.radius_lg', 'label' => 'Large radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.radius_xl', 'label' => 'Extra-large radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.radius_2xl', 'label' => 'Ultra radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.button_radius', 'label' => 'Button radius', 'hint' => 'For a fully pill-shaped button, enter 999px manually.', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.input_radius', 'label' => 'Input radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.card_radius', 'label' => 'Card radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.sidebar_radius', 'label' => 'Sidebar radius', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.shape.border_width', 'label' => 'Border widths', 'control' => 'range', 'min' => 0, 'max' => 8, 'step' => 1, 'unit' => 'px', 'rules' => $len],
            ],
        ],

        'layout' => [
            'label' => 'Layout & sizing',
            'icon' => 'fa-columns',
            'fields' => [
                ['path' => 'theme.scale.density', 'label' => 'Density', 'hint' => 'Tightens or loosens the spacing of the whole interface at once.', 'control' => 'select', 'rules' => ['required', 'in:compact,normal,spacious'],
                    'options' => ['compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Relaxed']],
                ['path' => 'theme.scale.container_width', 'label' => 'Content width', 'hint' => 'The width of the main content column on pages.', 'control' => 'select', 'rules' => ['required', 'in:narrow,content,wide,full'],
                    'options' => ['narrow' => 'Narrow', 'content' => 'Standard', 'wide' => 'Wide', 'full' => 'Full width']],
                ['path' => 'theme.scale.section_rhythm', 'label' => 'Section rhythm', 'hint' => 'Vertical space between the home page sections.', 'control' => 'range', 'min' => 2, 'max' => 16, 'step' => 0.5, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.layout.hero_ratio', 'label' => 'Hero column ratio', 'hint' => 'How the width is split between the text and image columns in the hero.', 'control' => 'select', 'rules' => ['required', 'in:50-50,60-40,40-60,70-30,30-70'],
                    'options' => ['50-50' => '50-50', '60-40' => '60-40', '40-60' => '40-60', '70-30' => '70-30', '30-70' => '30-70']],
                ['path' => 'theme.layout.card_gap', 'label' => 'Card gap', 'control' => 'range', 'min' => 0, 'max' => 64, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.content_max_width', 'label' => 'Max content width', 'control' => 'range', 'min' => 640, 'max' => 1920, 'step' => 20, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.content_padding', 'label' => 'Content padding', 'control' => 'range', 'min' => 0, 'max' => 96, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.sidebar_width', 'label' => 'Panel sidebar width', 'control' => 'range', 'min' => 160, 'max' => 400, 'step' => 4, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.topnav_height', 'label' => 'Panel top bar height', 'control' => 'range', 'min' => 40, 'max' => 120, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.layout.shell_nav', 'label' => 'Panel menu position', 'hint' => 'The panel navigation structure: a horizontal top bar or a side column.', 'control' => 'select', 'rules' => ['required', 'in:topnav,sidebar'],
                    'options' => ['topnav' => 'Top bar', 'sidebar' => 'Sidebar']],
                ['path' => 'theme.landing.nav_height', 'label' => 'Site menu height', 'control' => 'range', 'min' => 2.5, 'max' => 8, 'step' => 0.25, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.landing.hero_min_height', 'label' => 'Hero min height', 'hint' => 'svh is a percentage of the viewport height; the hero never gets shorter than this.', 'control' => 'range', 'min' => 40, 'max' => 100, 'step' => 1, 'unit' => 'svh', 'rules' => $lenView],
            ],
        ],

        'background' => [
            'label' => 'Background',
            'icon' => 'fa-image',
            'fields' => [
                ['path' => 'theme.background.mode', 'label' => 'Background mode', 'hint' => 'The main role behind the whole page; some modes are built from the brand colors.', 'control' => 'select', 'rules' => ['required', 'in:glow,flat,gradient,mesh,grid,dots,noise,beams,aurora,stripes'],
                    'options' => ['glow' => 'Glow', 'flat' => 'Flat', 'gradient' => 'Gradient', 'mesh' => 'Mesh', 'grid' => 'Grid', 'dots' => 'Dots', 'noise' => 'Noise', 'beams' => 'Beams', 'aurora' => 'Aurora', 'stripes' => 'Stripes']],
                ['path' => 'theme.background.gradient_angle', 'label' => 'Gradient angle', 'control' => 'range', 'min' => 0, 'max' => 360, 'step' => 1, 'unit' => 'deg', 'rules' => ['required', 'string', 'max:10', 'regex:/^[0-9]{1,3}deg$/']],
                ['path' => 'theme.background.section_alternation', 'label' => 'Section alternation', 'hint' => 'Alternating home sections get a different background.', 'control' => 'select', 'rules' => ['required', 'in:none,tint,surface-alt,rule'],
                    'options' => ['none' => 'None', 'tint' => 'Tint change', 'surface-alt' => 'Alternate surface', 'rule' => 'Divider rule']],
                ['path' => 'theme.background.noise', 'label' => 'Background noise (0 to 1)', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.background.grid_size', 'label' => 'Grid cell size', 'control' => 'range', 'min' => 8, 'max' => 120, 'step' => 2, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.glow_size', 'label' => 'Glow size', 'hint' => 'The size of the glow behind the hero content.', 'control' => 'range', 'min' => 4, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'theme.landing.glow_blur', 'label' => 'Glow blur', 'control' => 'range', 'min' => 0, 'max' => 300, 'step' => 5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.glow_opacity', 'label' => 'Glow intensity (0 to 1)', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'rules' => $num],
            ],
        ],

        'motion' => [
            'label' => 'Motion & animation',
            'icon' => 'fa-wind',
            'fields' => [
                ['path' => 'theme.motion.reveal', 'label' => 'Scroll reveal', 'hint' => 'How sections enter when they scroll into view.', 'control' => 'select',
                    'rules' => ['required', 'in:fade-up,fade-down,fade-left,fade-right,zoom,blur-in,line-mask,rise,drop,flip,wipe,skew-in,none'],
                    'options' => ['fade-up' => 'Fade up', 'fade-down' => 'Fade down', 'fade-left' => 'Fade left', 'fade-right' => 'Fade right',
                        'zoom' => 'Zoom', 'blur-in' => 'Blur in', 'line-mask' => 'Line mask', 'rise' => 'Rise', 'drop' => 'Drop', 'flip' => '3D flip',
                        'wipe' => 'Wipe', 'skew-in' => 'Skew in', 'none' => 'None']],
                ['path' => 'theme.motion.stagger', 'label' => 'Card reveal order', 'control' => 'select', 'rules' => ['required', 'in:sequential,none'],
                    'options' => ['sequential' => 'Staggered', 'none' => 'Simultaneous']],
                ['path' => 'theme.motion.parallax', 'label' => 'Parallax', 'hint' => 'Decorative elements move slowly while scrolling.', 'control' => 'select', 'rules' => ['required', 'in:none,subtle,strong'],
                    'options' => ['none' => 'None', 'subtle' => 'Subtle', 'strong' => 'Strong']],
                ['path' => 'theme.motion.duration_scale', 'label' => 'Animation speed factor', 'hint' => 'The duration multiplier for every animation; a bigger number is slower.', 'control' => 'range', 'min' => 0.25, 'max' => 3, 'step' => 0.05, 'unit' => '', 'rules' => $num],
                ['path' => 'theme.motion.easing', 'label' => 'Easing', 'control' => 'text', 'rules' => ['required', 'string', 'max:60', 'regex:/^[a-zA-Z0-9(),.\s-]+$/']],
                ['path' => 'theme.motion.hover', 'label' => 'Card hover', 'hint' => 'How cards react when the mouse moves over them.', 'control' => 'select',
                    'rules' => ['required', 'in:lift,glow,scale,border,skew,shadow-grow,shift,icon-spin,invert,underline,none'],
                    'options' => ['lift' => 'Lift', 'glow' => 'Glow', 'scale' => 'Scale', 'border' => 'Border', 'skew' => 'Skew',
                        'shadow-grow' => 'Shadow grow', 'shift' => 'Shift', 'icon-spin' => 'Icon spin', 'invert' => 'Invert', 'underline' => 'Underline', 'none' => 'None']],
                ['path' => 'theme.motion.text_effect', 'label' => 'Hero heading effect', 'hint' => 'An animation on the main hero heading.', 'control' => 'select', 'rules' => ['required', 'in:none,gradient-shift,glow-pulse'],
                    'options' => ['none' => 'None', 'gradient-shift' => 'Gradient shift', 'glow-pulse' => 'Glow pulse']],
                ['path' => 'theme.motion.scroll_progress', 'label' => 'Scroll progress bar', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.marquee_pause', 'label' => 'Pause marquee on hover', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.marquee_speed', 'label' => 'Marquee period', 'hint' => 'The time for one full marquee loop; less means faster.', 'control' => 'range', 'min' => 2, 'max' => 120, 'step' => 1, 'unit' => 's', 'rules' => $dur],
                ['path' => 'theme.motion.tilt', 'label' => '3D card tilt', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.motion.magnetic', 'label' => 'Magnetic buttons', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.effects.enable_animations', 'label' => 'All animations on', 'hint' => 'The master switch; turning it off disables every animation.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'theme.effects.hover_lift', 'label' => 'Hover lift height', 'control' => 'range', 'min' => 0, 'max' => 32, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.effects.animation_duration', 'label' => 'Micro-animation duration', 'control' => 'range', 'min' => 50, 'max' => 1000, 'step' => 10, 'unit' => 'ms', 'rules' => $dur],
                ['path' => 'theme.landing.reveal_duration', 'label' => 'Reveal duration', 'control' => 'range', 'min' => 100, 'max' => 3000, 'step' => 50, 'unit' => 'ms', 'rules' => $dur],
                ['path' => 'theme.landing.reveal_offset', 'label' => 'Reveal start offset', 'control' => 'range', 'min' => 0, 'max' => 200, 'step' => 5, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.stagger_ms', 'label' => 'Card stagger delay (ms)', 'control' => 'range', 'min' => 0, 'max' => 2000, 'step' => 50, 'unit' => '', 'rules' => ['required', 'integer', 'min:0', 'max:2000']],
                ['path' => 'theme.landing.float_distance', 'label' => 'Float amplitude', 'control' => 'range', 'min' => 0, 'max' => 48, 'step' => 1, 'unit' => 'px', 'rules' => $len],
                ['path' => 'theme.landing.float_duration', 'label' => 'Float period', 'control' => 'range', 'min' => 1, 'max' => 30, 'step' => 0.5, 'unit' => 's', 'rules' => $dur],
                ['path' => 'theme.landing.counter_duration', 'label' => 'Counter duration (ms)', 'control' => 'range', 'min' => 0, 'max' => 20000, 'step' => 500, 'unit' => '', 'rules' => ['required', 'integer', 'min:0', 'max:20000']],
            ],
        ],

        'decoration' => [
            'label' => 'Ornament',
            'icon' => 'fa-shapes',
            'fields' => [
                ['path' => 'theme.decoration.section_divider', 'label' => 'Section divider', 'control' => 'select',
                    'rules' => ['required', 'in:none,line,double-line,dots,slant,curve,zigzag,gradient-band'],
                    'options' => ['none' => 'None', 'line' => 'Line', 'double-line' => 'Double line', 'dots' => 'Dotted', 'slant' => 'Slant',
                        'curve' => 'Curve', 'zigzag' => 'Zigzag', 'gradient-band' => 'Gradient band']],
                ['path' => 'theme.decoration.heading_rule', 'label' => 'Heading rule', 'control' => 'select',
                    'rules' => ['required', 'in:none,short-bar,thick-underline,full-line,number,side-rules,dot,eyebrow-pill'],
                    'options' => ['none' => 'None', 'short-bar' => 'Short bar', 'thick-underline' => 'Thick underline', 'full-line' => 'Full line',
                        'number' => 'Number', 'side-rules' => 'Side rules', 'dot' => 'Dots', 'eyebrow-pill' => 'Eyebrow pill']],
                ['path' => 'theme.decoration.heading_align', 'label' => 'Heading alignment', 'control' => 'select', 'rules' => ['required', 'in:start,center,end,between'],
                    'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End', 'between' => 'Space between']],
                ['path' => 'theme.decoration.accent_shapes', 'label' => 'Accent shapes', 'control' => 'select', 'rules' => ['required', 'in:none,plus,dots,grid,corner-brackets'],
                    'options' => ['none' => 'None', 'plus' => 'Plus', 'dots' => 'Dots', 'grid' => 'Grid', 'corner-brackets' => 'Corner brackets']],
                ['path' => 'theme.decoration.quote_mark', 'label' => 'Oversized', 'control' => 'select', 'rules' => ['required', 'in:none,serif,brand,oversized'],
                    'options' => ['none' => 'None', 'serif' => 'Serif', 'brand' => 'Brand color', 'oversized' => 'Oversized']],
                ['path' => 'theme.decoration.icon_backdrop', 'label' => 'Icon backdrop', 'control' => 'select',
                    'rules' => ['required', 'in:soft-square,soft-circle,square,circle,diamond,hexagon,squircle,ring,outline,gradient,none'],
                    'options' => ['soft-square' => 'Soft square', 'soft-circle' => 'Soft circle', 'square' => 'Square', 'circle' => 'Circle',
                        'diamond' => 'Diamond', 'hexagon' => 'Hexagon', 'squircle' => 'Squircle', 'ring' => 'Ring', 'outline' => 'Outline', 'gradient' => 'Gradient', 'none' => 'None']],
                ['path' => 'theme.decoration.card_edge', 'label' => 'Card edge', 'control' => 'select', 'rules' => ['required', 'in:none,top-accent,left-accent,corner-cut,glow-border'],
                    'options' => ['none' => 'Plain', 'top-accent' => 'Top bar', 'left-accent' => 'Side accent', 'corner-cut' => 'Corner cut', 'glow-border' => 'Glow border']],
            ],
        ],

        'buttons' => [
            'label' => 'Buttons',
            'icon' => 'fa-hand-pointer',
            'fields' => [
                ['path' => 'theme.buttons.variant', 'label' => 'Button style', 'hint' => 'The base style of every button on the site and panel.', 'control' => 'select',
                    'rules' => ['required', 'in:solid,outline,soft,ghost,gradient,glass,brutal,underline'],
                    'options' => ['solid' => 'Filled', 'outline' => 'Outline', 'soft' => 'Soft', 'ghost' => 'Subtle glass',
                        'gradient' => 'Gradient', 'glass' => 'Glass', 'brutal' => 'Brutal', 'underline' => 'Underlined']],
                ['path' => 'theme.buttons.size', 'label' => 'Button size', 'control' => 'select', 'rules' => ['required', 'in:sm,md,lg'],
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
                ['path' => 'theme.buttons.weight', 'label' => 'Button font weight', 'control' => 'select', 'rules' => ['required', $weights['in']], 'options' => $weights['options']],
                ['path' => 'theme.buttons.transform', 'label' => 'Button case', 'control' => 'select', 'rules' => ['required', 'in:none,uppercase,capitalize,lowercase'],
                    'options' => ['none' => 'Normal', 'uppercase' => 'Uppercase', 'capitalize' => 'Capitalize', 'lowercase' => 'Lowercase']],
                ['path' => 'theme.buttons.letter_spacing', 'label' => 'Button letter spacing', 'control' => 'text', 'rules' => $ls],
                ['path' => 'theme.buttons.icon', 'label' => 'Default button icon', 'control' => 'select',
                    'rules' => ['required', 'in:none,arrow,arrow-ltr,arrow-down,chevron,plus,sparkle,download,play'],
                    'options' => ['none' => 'None', 'arrow' => 'Arrow (follows text)', 'arrow-ltr' => 'Arrow right', 'arrow-down' => 'Arrow down',
                        'chevron' => 'Chevron', 'plus' => 'Plus', 'sparkle' => 'Glow', 'download' => 'Download', 'play' => 'Play']],
                ['path' => 'theme.buttons.shadow', 'label' => 'Button shadow', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'accessibility' => [
            'label' => 'Accessibility',
            'icon' => 'fa-universal-access',
            'fields' => [
                ['path' => 'theme.accessibility.contrast', 'label' => 'Contrast', 'hint' => 'The “High” mode makes muted text and borders stronger.', 'control' => 'select', 'rules' => ['required', 'in:normal,high'],
                    'options' => ['normal' => 'Normal', 'high' => 'High']],
                ['path' => 'theme.accessibility.focus_ring', 'label' => 'Focus mode', 'control' => 'select', 'rules' => ['required', 'in:outline,glow,underline,none'],
                    'options' => ['outline' => 'Border', 'glow' => 'Glow', 'underline' => 'Underline', 'none' => 'None']],
                ['path' => 'theme.accessibility.reduce_motion', 'label' => 'Respect reduced motion', 'hint' => 'Decides what to do when the user has enabled “reduce motion” on their device.', 'control' => 'select', 'rules' => ['required', 'in:respect,force-off,force-on'],
                    'options' => ['respect' => 'Follow system setting', 'force-off' => 'Always off', 'force-on' => 'Always on']],
            ],
        ],

        'i18n' => [
            'label' => 'Numerals',
            'icon' => 'fa-language',
            'fields' => [
                ['path' => 'theme.i18n.numerals', 'label' => 'Digits', 'hint' => 'Auto means Persian digits in Persian text and Latin digits in Latin text.', 'control' => 'select', 'rules' => ['required', 'in:auto,fa,en'],
                    'options' => ['auto' => 'Auto', 'fa' => 'Persian', 'en' => 'English']],
            ],
        ],

        'variants' => [
            'label' => 'Section templates',
            'icon' => 'fa-puzzle-piece',
            'hint' => 'Each home section has several ready layouts; switching a template never touches your text.',
            'fields' => [
                ['path' => 'public.landing.hero.layout', 'label' => 'Hero template', 'control' => 'select', 'rules' => ['required', 'in:default,centered-stack,oversized-type'],
                    'options' => ['default' => 'Two columns', 'centered-stack' => 'Centered stack', 'oversized-type' => 'Giant typography']],
                ['path' => 'public.landing.advisor.variant', 'label' => 'Advisor template', 'control' => 'select', 'rules' => ['required', 'in:default,quote-first'],
                    'options' => ['default' => 'Classic', 'quote-first' => 'Quote-focused']],
                ['path' => 'public.landing.ecosystem.variant', 'label' => 'Ecosystem template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
                ['path' => 'public.landing.services.variant', 'label' => 'Services template', 'control' => 'select', 'rules' => ['required', 'in:default,numbered-list,bento'],
                    'options' => ['default' => 'Card grid', 'numbered-list' => 'Numbered list', 'bento' => 'Bento']],
                ['path' => 'public.landing.stats.variant', 'label' => 'Stats template', 'control' => 'select', 'rules' => ['required', 'in:default,inline-divider,band,boxed'],
                    'options' => ['default' => 'Default', 'inline-divider' => 'With divider', 'band' => 'Full-width band', 'boxed' => 'Framed']],
                ['path' => 'public.landing.testimonials.variant', 'label' => 'Testimonials template', 'control' => 'select', 'rules' => ['required', 'in:default,single-featured,marquee,masonry'],
                    'options' => ['default' => 'Grid', 'single-featured' => 'One featured quote', 'marquee' => 'Animated', 'masonry' => 'Masonry']],
                ['path' => 'public.landing.blog.variant', 'label' => 'Blog template', 'control' => 'select', 'rules' => ['required', 'in:default,list'],
                    'options' => ['default' => 'Card grid', 'list' => 'List']],
                ['path' => 'public.landing.cta.variant', 'label' => 'Call-to-action template', 'control' => 'select', 'rules' => ['required', 'in:default,boxed-card,split'],
                    'options' => ['default' => 'Default', 'boxed-card' => 'Boxed card', 'split' => 'Two columns']],
                ['path' => 'public.landing.logos.variant', 'label' => 'Logos template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
                ['path' => 'public.landing.process.variant', 'label' => 'Process template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
                ['path' => 'public.landing.faq.variant', 'label' => 'FAQ template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
                ['path' => 'public.landing.comparison.variant', 'label' => 'Comparison template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
                ['path' => 'public.landing.blocks.variant', 'label' => 'Blocks template', 'control' => 'select', 'rules' => ['required', 'in:default'],
                    'options' => ['default' => 'Default']],
            ],
        ],

        'landing' => [
            'label' => 'Home page structure',
            'icon' => 'fa-sitemap',
            'fields' => [
                ['path' => 'public.landing.sections', 'label' => 'Home page sections', 'hint' => 'Order and presence of the sections on the home page. The hero is locked: always first, always shown.', 'control' => 'sections', 'rules' => ['required', 'array', 'min:1'],
                    'locked' => ['hero'],
                    'options' => [
                        'hero' => 'Hero', 'advisor' => 'Advisor', 'ecosystem' => 'Ecosystem', 'services' => 'Services',
                        'stats' => 'Stats', 'testimonials' => 'Testimonials', 'blog' => 'Blog', 'cta' => 'Call to action',
                        'logos' => 'Logos', 'process' => 'Process', 'faq' => 'FAQ', 'comparison' => 'Comparison',
                        'blocks' => 'Custom blocks',
                    ]],
                ['path' => 'public.landing.meta.title', 'label' => 'Meta title (SEO)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.meta.description', 'label' => 'Meta description', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.meta.og_image', 'label' => 'Share image (og:image)', 'hint' => 'The image shown when the link is shared on social networks.', 'control' => 'image', 'folder' => 'meta', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048']],
                ['path' => 'public.landing.animations.reveal', 'label' => 'Scroll reveal animation', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.animations.float', 'label' => 'Hero card float', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.animations.counters', 'label' => 'Live stats counter', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.blog.count', 'label' => 'Home page article count', 'control' => 'range', 'min' => 1, 'max' => 12, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:12']],
                ['path' => 'public.landing.services.columns', 'label' => 'Services columns', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.stats.columns', 'label' => 'Stats columns', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.testimonials.columns', 'label' => 'Testimonials columns (empty = auto)', 'hint' => 'Leaving it empty means it is derived from the selected template.', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:6']],
                ['path' => 'public.landing.blog.columns', 'label' => 'Blog columns', 'control' => 'range', 'min' => 1, 'max' => 6, 'step' => 1, 'unit' => '', 'rules' => ['required', 'integer', 'min:1', 'max:6']],
            ],
        ],

        'hero' => [
            'label' => 'Hero copy',
            'icon' => 'fa-bullhorn',
            'fields' => [
                ['path' => 'public.landing.hero.title_line1', 'label' => 'Heading line 1', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.hero.title_line2', 'label' => 'Heading line 2 (brand)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.hero.subtitle', 'label' => 'Subheading', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.hero.eyebrow', 'label' => 'Eyebrow above heading', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.hero.gradient_text', 'label' => 'Gradient on line 2', 'hint' => 'The second heading line is painted with the brand color gradient.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.hero.gradient_dir', 'label' => 'Heading gradient direction', 'control' => 'select', 'rules' => ['required', 'in:to-l,to-r'],
                    'options' => ['to-l' => 'To the left', 'to-r' => 'To the right']],
                ['path' => 'public.landing.hero.text_align', 'label' => 'Hero text alignment', 'control' => 'select', 'rules' => ['required', 'in:start,center,end'],
                    'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End']],
                ['path' => 'public.landing.hero.text_side', 'label' => 'Text column side', 'hint' => 'Whether the text column starts at the beginning (right) or the end (left) of the page.', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'Start', 'end' => 'End']],
                ['path' => 'public.landing.hero.mockup', 'label' => 'Show dashboard mockup', 'hint' => 'Show a fake dashboard image next to the hero heading.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.hero.media', 'label' => 'Second column media', 'hint' => 'Decides what the hero’s second column shows.', 'control' => 'select', 'rules' => ['required', 'in:mockup,photo,none'],
                    'options' => ['mockup' => 'Mockup', 'photo' => 'Custom image', 'none' => 'None']],
                ['path' => 'public.landing.hero.image', 'label' => 'Hero image', 'control' => 'image', 'folder' => 'hero', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'public.landing.hero.image_alt', 'label' => 'Hero image alt text', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.hero.buttons', 'label' => 'Hero buttons', 'hint' => 'Each row is one button; “Visible” hides it temporarily. Clearing the whole list restores the base file’s default buttons.', 'control' => 'list', 'max' => 4, 'rules' => ['nullable', 'array', 'max:4'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'URL', 'control' => 'text', 'rules' => $href],
                        ['key' => 'style', 'label' => 'Style', 'control' => 'select', 'rules' => ['required', 'in:solid,ghost,primary,outline'], 'options' => $btnStyle],
                        ['key' => 'icon', 'label' => 'Icon', 'control' => 'select', 'rules' => ['nullable', 'string', 'max:20'], 'options' => $btnIcon],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'advisor' => [
            'label' => 'Advisor text',
            'icon' => 'fa-user-tie',
            'fields' => [
                ['path' => 'public.landing.advisor.name', 'label' => 'Advisor name', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.tagline', 'label' => 'Advisor tagline', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.tagline_color', 'label' => 'Tagline color', 'control' => 'select',
                    'rules' => ['required', 'in:primary,secondary,accent_blue,accent_emerald,accent_orange,accent_teal,accent_red,accent_violet,accent_pink,accent_lime,accent_cyan,accent_amber,accent_rose'],
                    'options' => ['primary' => 'Primary color', 'secondary' => 'Secondary color', 'accent_blue' => 'Accent blue', 'accent_emerald' => 'Accent emerald',
                        'accent_orange' => 'Accent orange', 'accent_teal' => 'Accent teal', 'accent_red' => 'Accent red', 'accent_violet' => 'Accent violet',
                        'accent_pink' => 'Accent pink', 'accent_lime' => 'Accent lime', 'accent_cyan' => 'Accent sky blue', 'accent_amber' => 'Accent amber',
                        'accent_rose' => 'Accent rose']],
                ['path' => 'public.landing.advisor.bio', 'label' => 'Biography', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                ['path' => 'public.landing.advisor.eyebrow.label', 'label' => 'Section eyebrow', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.eyebrow.icon', 'label' => 'Class icon (Font Awesome)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/']],
                ['path' => 'public.landing.advisor.badge.label', 'label' => 'Badge text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.badge.visible', 'label' => 'Show badge', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.image', 'label' => 'Advisor image', 'control' => 'image', 'folder' => 'advisor', 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048']],
                ['path' => 'public.landing.advisor.image_alt', 'label' => 'Image alt text', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.advisor.image_side', 'label' => 'Image side', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'Start', 'end' => 'End']],
                ['path' => 'public.landing.advisor.image_size', 'label' => 'Image size', 'control' => 'range', 'min' => 8, 'max' => 40, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.landing.advisor.spin_rings', 'label' => 'Rotating rings around image', 'hint' => 'Decorative rotating rings around the advisor image.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.grayscale', 'label' => 'Grayscale image', 'hint' => 'The advisor image is shown in black and white.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.advisor.buttons', 'label' => 'Advisor buttons', 'control' => 'list', 'max' => 3, 'rules' => ['nullable', 'array', 'max:3'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'URL', 'control' => 'text', 'rules' => $href],
                        ['key' => 'style', 'label' => 'Style', 'control' => 'select', 'rules' => ['required', 'in:primary,ghost,solid,outline'], 'options' => $btnStyle],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'sections_content' => [
            'label' => 'Section copy',
            'icon' => 'fa-align-left',
            'fields' => [
                ['path' => 'public.landing.ecosystem.heading', 'label' => 'Ecosystem: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.ecosystem.text', 'label' => 'Ecosystem: text', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                ['path' => 'public.landing.ecosystem.text_side', 'label' => 'Ecosystem: text side', 'control' => 'select', 'rules' => ['required', 'in:start,end'],
                    'options' => ['start' => 'Start', 'end' => 'End']],
                ['path' => 'public.landing.ecosystem.visual', 'label' => 'Ecosystem: graphic panel', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.services.heading', 'label' => 'Services: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.services.subheading', 'label' => 'Services: subheading', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.testimonials.heading', 'label' => 'Testimonials: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.testimonials.subheading', 'label' => 'Testimonials: subheading', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.blog.heading', 'label' => 'Blog: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.blog.subheading', 'label' => 'Blog: subheading', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.blog.see_all.label', 'label' => 'Blog: “View all” text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.blog.see_all.visible', 'label' => 'Blog: show “View all” link', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.landing.cta.heading', 'label' => 'Call to action: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.cta.text', 'label' => 'Call to action: text', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.landing.logos.heading', 'label' => 'Logos: heading', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:255']],
                ['path' => 'public.landing.process.heading', 'label' => 'Process: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.process.subheading', 'label' => 'Process: subheading', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.faq.heading', 'label' => 'FAQ: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.faq.subheading', 'label' => 'FAQ: subheading', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.comparison.heading', 'label' => 'Comparison: heading', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.landing.comparison.subheading', 'label' => 'Comparison: subheading', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:2000']],
                ['path' => 'public.landing.services.items', 'label' => 'Service cards', 'hint' => 'Add, remove and reorder cards; “Visible” toggles each card on its own.', 'control' => 'list', 'max' => 12, 'rules' => ['nullable', 'array', 'max:12'],
                    'item' => [
                        ['key' => 'title', 'label' => 'Title', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'Description', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'icon', 'label' => 'Icon (Font Awesome)', 'control' => 'text', 'rules' => $faClass],
                        ['key' => 'accent', 'label' => 'Color', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.ecosystem.items', 'label' => 'Ecosystem items', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'icon', 'label' => 'Symbol', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:8']],
                        ['key' => 'accent', 'label' => 'Color', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.stats.items', 'label' => 'Stats counters', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'value', 'label' => 'Value', 'control' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:999999999']],
                        ['key' => 'suffix', 'label' => 'Suffix', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:10']],
                        ['key' => 'gradient', 'label' => 'Gradient', 'control' => 'toggle'],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.testimonials.items', 'label' => 'Testimonial quotes', 'control' => 'list', 'max' => 12, 'rules' => ['nullable', 'array', 'max:12'],
                    'item' => [
                        ['key' => 'name', 'label' => 'Name', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'result', 'label' => 'Result', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'initials', 'label' => 'Initials', 'control' => 'text', 'rules' => ['required', 'string', 'max:10']],
                        ['key' => 'text', 'label' => 'Quote text', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'from', 'label' => 'Gradient from', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'to', 'label' => 'Gradient to', 'control' => 'select', 'rules' => ['required', $accentsIn], 'options' => $accents],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.faq.items', 'label' => 'FAQ items', 'control' => 'list', 'max' => 20, 'rules' => ['nullable', 'array', 'max:20'],
                    'item' => [
                        ['key' => 'question', 'label' => 'Question', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'answer', 'label' => 'Answer', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:4000']],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.process.items', 'label' => 'Process steps', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'title', 'label' => 'Title', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'Description', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:1000']],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
                ['path' => 'public.landing.cta.buttons', 'label' => 'Call-to-action buttons', 'control' => 'list', 'max' => 3, 'rules' => ['nullable', 'array', 'max:3'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'route', 'label' => 'Destination', 'control' => 'select', 'rules' => ['required', 'in:'.implode(',', array_keys($siteRoutes))], 'options' => $siteRoutes],
                        ['key' => 'style', 'label' => 'Style', 'control' => 'select', 'rules' => ['required', 'in:solid,ghost,primary,outline'], 'options' => $btnStyle],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'blocks' => [
            'label' => 'Custom blocks',
            'icon' => 'fa-cubes',
            'hint' => 'A free section you compose yourself from heading, text, button, card, image and spacer. The row type decides which fields it has; then add the section from “Home page structure”.',
            'fields' => [
                ['path' => 'public.landing.blocks.items', 'label' => 'Blocks', 'hint' => 'Each row is one block; “Visible” hides it temporarily. Clearing the whole list shows no blocks on the page.', 'control' => 'list', 'max' => 24, 'rules' => ['nullable', 'array', 'max:24'],
                    'item' => [
                        ['key' => 'type', 'label' => 'Block type', 'control' => 'select', 'discriminant' => true,
                            'rules' => ['required', 'in:'.implode(',', array_keys($blockTypes))], 'options' => $blockTypes],
                        ['key' => 'title', 'label' => 'Heading / button label', 'control' => 'text', 'show_for' => ['heading', 'card', 'button'], 'required_for' => ['heading', 'card', 'button'],
                            'rules' => ['nullable', 'string', 'max:255']],
                        ['key' => 'text', 'label' => 'Text', 'control' => 'textarea', 'show_for' => ['heading', 'text', 'card', 'image'], 'required_for' => ['text'],
                            'rules' => ['nullable', 'string', 'max:4000']],
                        ['key' => 'href', 'label' => 'URL', 'control' => 'text', 'show_for' => ['button'], 'required_for' => ['button'],
                            'rules' => $hrefOpt],
                        ['key' => 'style', 'label' => 'Style', 'control' => 'select', 'show_for' => ['button'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($btnStyleOpt))], 'options' => $btnStyleOpt],
                        ['key' => 'icon', 'label' => 'Icon', 'control' => 'select', 'show_for' => ['button'],
                            'rules' => ['nullable', 'string', 'max:20', 'in:'.implode(',', array_keys($btnIcon))], 'options' => $btnIcon],
                        ['key' => 'fa_icon', 'label' => 'Card icon (Font Awesome)', 'control' => 'text', 'show_for' => ['card'],
                            'rules' => $faClass],
                        ['key' => 'accent', 'label' => 'Accent color', 'control' => 'select', 'show_for' => ['card'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($accentsOpt))], 'options' => $accentsOpt],
                        ['key' => 'align', 'label' => 'Align', 'control' => 'select', 'show_for' => ['heading', 'text'],
                            'rules' => ['nullable', 'in:'.implode(',', array_keys($alignOpt))], 'options' => $alignOpt],
                        ['key' => 'src', 'label' => 'Image URL', 'control' => 'text', 'show_for' => ['image'], 'required_for' => ['image'],
                            'rules' => $imgSrc],
                        ['key' => 'size', 'label' => 'Spacer size', 'control' => 'select', 'content' => false, 'show_for' => ['spacer'],
                            'rules' => ['nullable', 'in:,sm,md,lg'],
                            'options' => ['' => 'Medium', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
                        ['key' => 'id', 'label' => 'Id', 'control' => 'hidden', 'content' => false,
                            'rules' => ['nullable', 'uuid']],
                        ['key' => 'background', 'label' => 'Block background', 'control' => 'color', 'content' => false,
                            'rules' => ['nullable', 'string', 'regex:/\A#[0-9A-Fa-f]{6}\z/']],
                        ['key' => 'color', 'label' => 'Block text color', 'control' => 'color', 'content' => false,
                            'rules' => ['nullable', 'string', 'regex:/\A#[0-9A-Fa-f]{6}\z/']],
                        ['key' => 'padding', 'label' => 'Padding (px)', 'control' => 'number', 'content' => false, 'min' => 0, 'max' => 128,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:0,128', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'radius', 'label' => 'Corner radius (px)', 'control' => 'number', 'content' => false, 'min' => 0, 'max' => 128,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:0,128', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'width', 'label' => 'Width (%)', 'control' => 'number', 'content' => false, 'min' => 10, 'max' => 100,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:10,100', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'overrides' => [
            'label' => 'Page element styles',
            'icon' => 'fa-palette',
            'hint' => 'The style of each individual element; select an element on the canvas and change its look right here. Leaving a value empty means “same as the rest of the template”.',
            // Driven from the canvas: its rows are written by the inspector, so
            // it gets no rail tab of its own and its controls are not offered
            // as a settings panel.
            'canvas_only' => true,
            'fields' => [
                ['path' => 'public.landing.overrides', 'label' => 'Element styles', 'hint' => 'Each row targets one page element; the element path fills itself in.', 'control' => 'list', 'max' => 64,
                    'rules' => ['nullable', 'array', 'max:64'],
                    'item' => [
                        ['key' => 'path', 'label' => 'Element path', 'control' => 'hidden',
                            'rules' => ['required', 'string', 'max:255', 'regex:/\Apublic\.(?:landing|nav|footer)(?:\.[a-z0-9_]+)+\z/']],
                        ['key' => 'background', 'label' => 'Background', 'control' => 'color', 'content' => false,
                            'rules' => ['nullable', 'string', 'regex:/\A#[0-9A-Fa-f]{6}\z/']],
                        ['key' => 'color', 'label' => 'Text color', 'control' => 'color', 'content' => false,
                            'rules' => ['nullable', 'string', 'regex:/\A#[0-9A-Fa-f]{6}\z/']],
                        ['key' => 'radius', 'label' => 'Corner radius (px)', 'control' => 'number', 'content' => false, 'min' => 0, 'max' => 128,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:0,128', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'padding', 'label' => 'Padding (px)', 'control' => 'number', 'content' => false, 'min' => 0, 'max' => 128,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:0,128', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'width', 'label' => 'Width (%)', 'control' => 'number', 'content' => false, 'min' => 10, 'max' => 100,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:10,100', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'font_scale', 'label' => 'Font scale (%)', 'control' => 'number', 'content' => false, 'min' => 50, 'max' => 400,
                            'rules' => ['nullable', 'numeric', 'integer', 'between:50,400', 'regex:/\A[0-9]+\z/']],
                        ['key' => 'align', 'label' => 'Align', 'control' => 'select', 'content' => false,
                            'options' => ['start' => 'Start', 'center' => 'Center', 'end' => 'End'],
                            'rules' => ['nullable', 'in:start,center,end']],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        // The canvas document model: one map keyed by element id, not a list.
        // A list cannot nest `states`/`breakpoints` under a row without
        // breaking Merge::structural's "lists replace wholesale" semantics, and
        // element ids (data-studio-path) contain dots, so a per-node dotted
        // path is not addressable through Arr::set. The whole map is stored as
        // one value at `public.canvas.nodes` and validated wholesale by
        // StudioStyles::cleanNodes — the same security boundary the legacy
        // `public.landing.overrides` list uses.
        //
        // canvas_only, like the overrides group: the canvas inspector writes
        // it, so it gets no rail tab. The deprecated `overrides` list stays
        // readable so existing tenants keep rendering.
        'canvas' => [
            'label' => 'Live canvas',
            'icon' => 'fa-vector-square',
            'hint' => 'The canvas document model: every element is a node keyed by its stable id; base styles, states and breakpoints are stored under that node.',
            'canvas_only' => true,
            'fields' => [
                ['path' => 'public.canvas.nodes', 'label' => 'Canvas nodes', 'control' => 'canvas',
                    'rules' => ['nullable', 'array']],
            ],
        ],

        'nav' => [
            'label' => 'Site menu',
            'icon' => 'fa-bars',
            'fields' => [
                ['path' => 'public.nav.enabled', 'label' => 'Show menu', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.style', 'label' => 'Menu material', 'hint' => 'Glass blurs what is behind the menu; transparent has no background at all.', 'control' => 'select', 'rules' => ['required', 'in:solid,glass,transparent'],
                    'options' => ['solid' => 'Matte', 'glass' => 'Glass', 'transparent' => 'Transparent']],
                ['path' => 'public.nav.sticky', 'label' => 'Sticky menu', 'hint' => 'The menu stays at the top of the page while scrolling.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.show_logo_mark', 'label' => 'Show logo mark in menu', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.links_style', 'label' => 'Menu link hover', 'control' => 'select', 'rules' => ['required', 'in:plain,underline,pill'],
                    'options' => ['plain' => 'Plain', 'underline' => 'Underline', 'pill' => 'Pill']],
                ['path' => 'public.nav.cta_style', 'label' => 'Login button style', 'control' => 'select', 'rules' => ['required', 'in:glass,solid,outline,gradient'],
                    'options' => ['glass' => 'Glass', 'solid' => 'Filled', 'outline' => 'Outline', 'gradient' => 'Gradient']],
                ['path' => 'public.nav.cta.label', 'label' => 'Login button label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.nav.cta.visible', 'label' => 'Show login button', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.nav.links', 'label' => 'Menu links', 'hint' => 'Home-page links are usually anchors; #, / and https URLs are all valid.', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'href', 'label' => 'URL', 'control' => 'text', 'rules' => $href],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'footer' => [
            'label' => 'Footer',
            'icon' => 'fa-shoe-prints',
            'fields' => [
                ['path' => 'public.footer.enabled', 'label' => 'Show footer', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.footer.show_logo_mark', 'label' => 'Show badge', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.footer.variant', 'label' => 'Footer layout', 'control' => 'select', 'rules' => ['required', 'in:columns,centered,minimal'],
                    'options' => ['columns' => 'Multi-column', 'centered' => 'Centered stack', 'minimal' => 'Minimal']],
                ['path' => 'public.footer.background', 'label' => 'Footer background', 'control' => 'select', 'rules' => ['required', 'in:background,surface,gradient'],
                    'options' => ['background' => 'Site background', 'surface' => 'Surface', 'gradient' => 'Brand gradient']],
                ['path' => 'public.footer.blurb', 'label' => 'Brand copy', 'control' => 'textarea', 'rules' => ['required', 'string', 'max:2000']],
                ['path' => 'public.footer.copyright', 'label' => 'Copyright text (:name and :year allowed)', 'hint' => '“:name” is replaced with the brand name and “:year” with the current year.', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.footer.social', 'label' => 'Social links', 'control' => 'list', 'max' => 8, 'rules' => ['nullable', 'array', 'max:8'],
                    'item' => [
                        ['key' => 'label', 'label' => 'Name', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                        ['key' => 'url', 'label' => 'URL', 'control' => 'text', 'rules' => $href],
                        ['key' => 'icon', 'label' => 'Icon (Font Awesome)', 'control' => 'text', 'rules' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9 \-]+$/']],
                        ['key' => 'visible', 'label' => 'Visible', 'control' => 'toggle'],
                    ]],
            ],
        ],

        'login' => [
            'label' => 'Login page',
            'icon' => 'fa-right-to-bracket',
            'fields' => [
                ['path' => 'public.login.title', 'label' => 'Title', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.subtitle', 'label' => 'Subtitle', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.email_label', 'label' => 'Email label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.password_label', 'label' => 'Password label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.remember_label', 'label' => '“Remember me” label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.submit_label', 'label' => 'Login button label', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.back_link.label', 'label' => 'Back link text', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.login.back_link.visible', 'label' => 'Show back link', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.login.card.max_width', 'label' => 'Login card width', 'control' => 'range', 'min' => 12, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.login.card.glass', 'label' => 'Glass card', 'hint' => 'The login card becomes glassy and what is behind it shows through blurred.', 'control' => 'toggle', 'rules' => $bool],
                ['path' => 'public.student_login.title', 'label' => 'Student login: title', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.subtitle', 'label' => 'Student login: subtitle', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.submit_label', 'label' => 'Student login: button', 'control' => 'text', 'rules' => ['required', 'string', 'max:255']],
                ['path' => 'public.student_login.card.max_width', 'label' => 'Student login: card width', 'control' => 'range', 'min' => 12, 'max' => 60, 'step' => 1, 'unit' => 'rem', 'rules' => $len],
                ['path' => 'public.student_login.card.glass', 'label' => 'Student login: glass card', 'control' => 'toggle', 'rules' => $bool],
            ],
        ],

        'features' => [
            'label' => 'Features',
            'icon' => 'fa-toggle-on',
            'hint' => 'Turning a key off removes that capability from this panel entirely.',
            'admin' => true,
            'fields' => [
                ['path' => 'features.dashboard', 'label' => 'Dashboard', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.blog_management', 'label' => 'Blog management', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.direct_chat', 'label' => 'Direct chat', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_chat', 'label' => 'Student chat', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.theme_studio', 'label' => 'Theme studio', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.bulk_actions', 'label' => 'Bulk actions', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.settings_chat', 'label' => 'Chat settings', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.settings_profile', 'label' => 'Profile settings', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.appearance_staff_publish', 'label' => 'Allow staff to publish appearance', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_profile', 'label' => 'Student profile', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.report_cards', 'label' => 'Report cards', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_exams', 'label' => 'Exams', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_schedule', 'label' => 'Weekly schedule', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.deals', 'label' => 'Renewal & payment', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.teacher_panel', 'label' => 'Teacher panel', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.teacher_materials', 'label' => 'Teacher materials', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.teacher_assignments', 'label' => 'Teacher assignments', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.teacher_schedule', 'label' => 'Teacher class schedule', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_materials', 'label' => 'Student materials', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_assignments', 'label' => 'Student assignments', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.student_timetable', 'label' => 'Class timetable', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.quiz_management', 'label' => 'Quiz management', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.question_management', 'label' => 'Question management', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.book_access', 'label' => 'Book access', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'features.create_post_action', 'label' => 'Create post button', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
            ],
        ],

        'chat_behavior' => [
            'label' => 'Chat behavior',
            'icon' => 'fa-comments',
            'hint' => 'Fine-grained chat settings; each feature’s main switches live in “Features”.',
            'admin' => true,
            'fields' => [
                ['path' => 'chat.groups.enabled', 'label' => 'Allow student groups', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'chat.groups.max_members', 'label' => 'Max members per group', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:2', 'max:500'], 'admin' => true],
                ['path' => 'chat.attachments.enabled', 'label' => 'File attachments', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'chat.attachments.max_kb', 'label' => 'Max file size (KB)', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:16', 'max:20480'], 'admin' => true],
                ['path' => 'chat.read_receipts', 'label' => 'Read receipts', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'chat.typing_indicator', 'label' => 'Typing indicator', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'chat.close_threads', 'label' => 'Allow closing chats', 'control' => 'toggle', 'rules' => $bool, 'admin' => true],
                ['path' => 'chat.message_max_length', 'label' => 'Max message length', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:100', 'max:20000'], 'admin' => true],
                ['path' => 'chat.rate_limit_per_minute', 'label' => 'Messages per minute', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:1', 'max:120'], 'admin' => true],
                ['path' => 'chat.idle_autoclose_days', 'label' => 'Auto-close after (days, 0 = off)', 'control' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:365'], 'admin' => true],
                ['path' => 'chat.greeting_text', 'label' => 'Chat greeting', 'hint' => 'The first thing a student sees in the chat room; empty means no greeting.', 'control' => 'textarea', 'rules' => ['nullable', 'string', 'max:500'], 'admin' => true],
                ['path' => 'chat.placeholder_text', 'label' => 'Message field placeholder', 'control' => 'text', 'rules' => ['nullable', 'string', 'max:200'], 'admin' => true],
            ],
        ],
    ],
];
