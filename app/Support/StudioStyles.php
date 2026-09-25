<?php

namespace App\Support;

/**
 * Per-element style overrides for the fixed sections.
 *
 * Custom blocks already carry their own `--block-*` variables (see
 * BlockStyles), but the sections the platform itself renders had no way to
 * restyle a single instance: recolouring one button meant either changing the
 * shared button token, which repaints every button on the site, or editing the
 * Blade template.
 *
 * Overrides are keyed by the element's `data-studio-path`, which every fixed
 * section now emits, and are published as one stylesheet rather than inline
 * styles on each element. That keeps the public markup untouched (no extra
 * attributes, no per-element style threading through the partials) and it
 * degrades to nothing when a tenant has no overrides.
 *
 * This class is the security boundary for that stylesheet: only a path that
 * still resolves against the studio schema, and only values with an exactly
 * known shape, may reach it. Anything else is dropped silently, so a stored
 * layer written by an older or tampered build can never emit arbitrary CSS.
 */
class StudioStyles
{
    /**
     * A path is only addressable if it is one the schema actually publishes:
     * an exact field, a row of a list field, or a container some field lives
     * under (nav.cta is a container while nav.cta.label is the field).
     *
     * At least one segment must follow the section root, so the page-level
     * roots themselves (`public.landing`, `public.nav`, `public.footer`) are
     * not addressable: they name no element.
     */
    public const PATH_PATTERN = '/\Apublic\.(?:landing|nav|footer|consultant|student|teacher)(?:\.[a-z0-9_]+)+\z/';

    /**
     * Page roots whose paths are SCHEMA-RESOLVED: validPath() must find a real
     * field, list row or container beneath them. The dashboard roots are
     * deliberately not here — they are template-owned ids (see isTemplatePath).
     */
    protected const SCHEMA_ROOTS = ['landing', 'nav', 'footer'];

    /**
     * Page roots whose paths are TEMPLATE-OWNED: the dashboard markup emits
     * them, but config/studio.php declares no fields for them (declaring dead
     * levers would violate the config's own "nothing consumes them yet" rule).
     * A path under these roots is addressable by shape alone, so the charset
     * whitelist below is the whole guarantee.
     */
    protected const TEMPLATE_ROOTS = ['consultant', 'student', 'teacher'];

    /**
     * A node id is the element's `data-studio-path`. It is stricter than a
     * path: it must be addressable (see validPath) and match the charset
     * whitelist that makes it safe to interpolate into an attribute selector.
     */
    public const NODE_ID_PATTERN = '/\A[a-z0-9_.]+\z/';

    /**
     * Pseudo-class states a node may carry a value for. Hover/active/focus/
     * focus-visible map straight to CSS pseudo-classes; `disabled` and
     * `selected` are attribute selectors, because CSS has no :disabled in this
     * app's markup contract for arbitrary elements.
     */
    protected const STATE_SELECTORS = [
        'hover' => ':hover',
        'active' => ':active',
        'focus' => ':focus',
        'focus_visible' => ':focus-visible',
        'disabled' => '[disabled]',
        'selected' => '[aria-selected="true"]',
    ];

    /**
     * Breakpoint => media query. Ordered narrow → wide so a wider rule (later
     * in the sheet) overrides a narrower one for the same property.
     */
    protected const BREAKPOINTS = [
        'mobile' => '(max-width:640px)',
        'tablet' => '(min-width:641px) and (max-width:1024px)',
        'desktop' => '(min-width:1025px)',
    ];

    /** Breakpoint keys in source order (narrow → wide). */
    protected const BREAKPOINT_ORDER = ['mobile', 'tablet', 'desktop'];

    /** The only stored node flags. */
    protected const NODE_FLAGS = ['locked', 'hidden'];

    /**
     * Style key => [CSS property, unit, min, max]. Numeric keys are clamped to
     * their range; colours must match a 6-digit hex. A key mapped to `choice`
     * accepts one of a fixed word list and is emitted verbatim.
     */
    protected const KEYS = [
        // Legacy seven — the original override-row vocabulary, unchanged so
        // existing stored rows keep round-tripping.
        'background' => ['background', '', 0, 0],
        'color' => ['color', '', 0, 0],
        'radius' => ['border-radius', 'px', 0, 128],
        'padding' => ['padding', 'px', 0, 128],
        'width' => ['inline-size', '%', 10, 100],
        'font_scale' => ['font-size', '%', 50, 400],
        'align' => ['text-align', 'choice', 0, 0],
        // Layout
        'position' => ['position', 'choice', 0, 0],
        'display' => ['display', 'choice', 0, 0],
        'justify_content' => ['justify-content', 'choice', 0, 0],
        'align_items' => ['align-items', 'choice', 0, 0],
        'flex_direction' => ['flex-direction', 'choice', 0, 0],
        'overflow' => ['overflow', 'choice', 0, 0],
        // Direct manipulation: signed offsets for positioned nodes, order for
        // flex/grid children, cell anchors for grid children.
        'top' => ['top', 'ipx', -3000, 3000],
        'left' => ['left', 'ipx', -3000, 3000],
        'order' => ['order', 'i', -999, 999],
        'grid_column' => ['grid-column', 'str', 0, 0],
        'grid_row' => ['grid-row', 'str', 0, 0],
        // Size / spacing
        'height' => ['block-size', 'str', 0, 0],
        'margin' => ['margin', 'px', 0, 256],
        'gap' => ['gap', 'px', 0, 128],
        // Typography
        'font_weight' => ['font-weight', 'choice', 0, 0],
        'line_height' => ['line-height', '%', 50, 300],
        'text_transform' => ['text-transform', 'choice', 0, 0],
        // Border / outline
        'border_width' => ['border-width', 'px', 0, 32],
        'border_style' => ['border-style', 'choice', 0, 0],
        'border_color' => ['border-color', '', 0, 0],
        'outline_width' => ['outline-width', 'px', 0, 32],
        'outline_style' => ['outline-style', 'choice', 0, 0],
        'outline_color' => ['outline-color', '', 0, 0],
        // Effects / motion — pattern-checked strings, never free CSS
        'box_shadow' => ['box-shadow', 'str', 0, 0],
        'opacity' => ['opacity', 'pct', 0, 100],
        'filter' => ['filter', 'str', 0, 0],
        'transform' => ['transform', 'str', 0, 0],
        'transition' => ['transition', 'choice', 0, 0],
    ];

    protected const ALIGN_CHOICES = ['start', 'center', 'end'];

    /**
     * Per-key choice lists for unit `choice`. A missing entry drops the value,
     * so a new choice key is inert until its list exists here.
     */
    protected const CHOICES = [
        'align' => self::ALIGN_CHOICES,
        'position' => ['static', 'relative', 'absolute', 'fixed', 'sticky'],
        'display' => ['block', 'inline', 'inline-block', 'flex', 'inline-flex', 'grid', 'inline-grid', 'flow-root'],
        'justify_content' => ['flex-start', 'flex-end', 'center', 'space-between', 'space-around', 'space-evenly'],
        'align_items' => ['stretch', 'center', 'flex-start', 'flex-end', 'baseline'],
        'flex_direction' => ['row', 'column', 'row-reverse', 'column-reverse'],
        'overflow' => ['visible', 'hidden', 'clip', 'auto', 'scroll'],
        'font_weight' => ['100', '200', '300', '400', '500', '600', '700', '800', '900'],
        'text_transform' => ['none', 'uppercase', 'lowercase', 'capitalize'],
        'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset'],
        'outline_style' => ['none', 'hidden', 'solid', 'dashed', 'dotted', 'double'],
        'transition' => [
            'color 0.15s ease',
            'color 0.2s ease-in-out',
            'background-color 0.2s ease',
            'transform 0.2s ease',
            'box-shadow 0.2s ease',
            'all 0.15s ease',
            'all 0.2s ease',
            'all 0.3s ease-in-out',
        ],
    ];

    /**
     * Charset patterns for unit `str`. Only these three keys accept strings,
     * and only shapes a composer can produce: a length with unit (or auto) for
     * size, and a letter/digit/punctuation allowlist for the composed
     * transform / filter / shadow — no quotes, no braces, no angle brackets,
     * nothing that can close the style block or open a selector.
     */
    protected const PATTERNS = [
        'height' => '/\A(?:auto|-?\d{1,4}(?:px|%|rem|em|vw|vh))\z/',
        'box_shadow' => '/\A(?:inset )?(?:0|-?\d{1,3}px) (?:0|-?\d{1,3}px) (?:0|-?\d{1,4}px)(?: (?:0|-?\d{1,3}px))? #[0-9A-Fa-f]{6}\z/',
        'transform' => '/\A[a-zA-Z0-9().,%\s-]*\z/',
        'filter' => '/\A[a-zA-Z0-9().,%\s-]*\z/',
        'grid_column' => '/\A(?:auto|-?\d{1,2}(?:\s*\/\s*(?:span\s+)?-?\d{1,2})?)\z/',
        'grid_row' => '/\A(?:auto|-?\d{1,2}(?:\s*\/\s*(?:span\s+)?-?\d{1,2})?)\z/',
    ];

    /**
     * Resolve a stored path against the studio schema. Exact fields, list rows
     * and container prefixes are addressable; everything else is not.
     */
    public static function validPath(string $path): bool
    {
        if (! preg_match(self::PATH_PATTERN, $path)) {
            return false;
        }

        // Template-owned roots (the dashboards) publish no schema fields; the
        // emitted ids are the contract, so the charset match above is enough.
        if (preg_match('/\Apublic\.(?:'.implode('|', self::TEMPLATE_ROOTS).')\./', $path)) {
            return true;
        }

        if (StudioSchema::field($path) !== null) {
            return true;
        }

        // A row of a list field: strip the numeric index and require a list.
        if (preg_match('/\A(.+)\.([0-9]+)\z/', $path, $m)) {
            $owner = StudioSchema::field($m[1]);

            return ($owner['control'] ?? '') === 'list';
        }

        // A container (nav.cta) with real fields beneath it.
        foreach (StudioSchema::fields() as $known => $field) {
            if (str_starts_with($known, $path.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stored-shape projection of a style row: only whitelisted keys whose
     * value has an exactly known shape survive, keeping the raw value (a bare
     * integer, a #rrggbb string, a choice word) or a valid token reference
     * (`['token' => 'colors.primary']`, any extra keys projected away). This
     * is the canonical form the canvas store persists, so re-validating a
     * stored node is idempotent.
     *
     * @return array<string, int|string|array{token: string}>
     */
    public static function validKeys(array $row): array
    {
        $out = [];

        foreach (self::KEYS as $key => [$property, $unit, $min, $max]) {
            $value = $row[$key] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            // A token reference: any key may bind to any catalog token — the
            // var name comes from the ThemeTokens whitelist, so what reaches
            // the stylesheet is `var(--safe-name)` and nothing else. Arrays
            // never fall through to scalar validation.
            if (is_array($value)) {
                $token = $value['token'] ?? null;

                if (is_string($token) && isset(ThemeTokens::VARS[$token])) {
                    $out[$key] = ['token' => $token];
                }

                continue;
            }

            if ($unit === 'choice') {
                $choices = self::CHOICES[$key] ?? [];

                if (is_string($value) && in_array($value, $choices, true)) {
                    $out[$key] = $value;
                }

                continue;
            }

            if ($unit === '') {
                if (is_string($value) && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value)) {
                    $out[$key] = $value;
                }

                continue;
            }

            if ($unit === 'str') {
                $pattern = self::PATTERNS[$key] ?? null;

                if (is_string($value) && $pattern !== null && preg_match($pattern, $value)) {
                    $out[$key] = $value;
                }

                continue;
            }

            // Signed integers: negative offsets for positioned nodes (`ipx`
            // emits with a px suffix) and free-standing signed numbers.
            if ($unit === 'i' || $unit === 'ipx') {
                if (is_numeric($value)
                    && preg_match('/\A-?\d+\z/', (string) $value)
                    && $value >= $min && $value <= $max) {
                    $out[$key] = (int) $value;
                }

                continue;
            }

            if (is_numeric($value)
                && preg_match('/\A[0-9]+\z/', (string) $value)
                && $value >= $min && $value <= $max) {
                $out[$key] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * The declarations a style row contributes, already whitelisted. Built from
     * validKeys(), so the stored shape and the emitted CSS can never disagree.
     * Token references emit `var(--…)` with the name from the ThemeTokens
     * catalog, so later token edits cascade into every bound element.
     * An empty result means the row carries no usable style.
     *
     * @return array<string, string>
     */
    public static function declarations(array $row): array
    {
        $out = [];

        foreach (self::validKeys($row) as $key => $value) {
            [$property, $unit] = self::KEYS[$key];

            if (is_array($value)) {
                $out[$property] = 'var(--'.ThemeTokens::VARS[$value['token']].')';

                continue;
            }

            $out[$property] = match ($unit) {
                'px', 'ipx' => $value.'px',
                '%' => $value.'%',
                // Stored 0–100, emitted as the unitless 0–1 CSS wants.
                'pct' => (string) ($value / 100),
                default => (string) $value,
            };
        }

        return $out;
    }

    /**
     * Build the stylesheet for a stored override list. Later rows win over
     * earlier ones, which is what makes "last edit applies" intuitive when two
     * rows somehow address the same element.
     *
     * @param  array  $rows  list of ['path' => string, style keys...]
     */
    public static function css(array $rows): string
    {
        $rules = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $path = $row['path'] ?? null;

            if (! is_string($path) || ! self::validPath($path)) {
                continue;
            }

            $declarations = self::declarations($row);

            if ($declarations === []) {
                continue;
            }

            $body = '';

            foreach ($declarations as $property => $value) {
                $body .= $property.':'.$value.';';
            }

            // The path charset is already pinned to [a-z0-9_.] by
            // validPath(), so it cannot break out of the attribute selector.
            $rules[$path] = '[data-studio-path="'.$path.'"]{'.$body.'}';
        }

        return $rules === [] ? '' : implode('', array_values($rules));
    }

    /**
     * Whether an id may be a canvas node: an addressable schema path, or a
     * section-marker id (`public.landing.hero` — a section root one segment
     * below the page root, which the schema does not publish as a field but
     * the dispatch templates do emit as `data-studio-section-marker`).
     *
     * Every accepted id matches NODE_ID_PATTERN and starts with a path root,
     * so it is safe to interpolate into a `[data-studio-path="…"]` selector.
     */
    public static function validNodeId(string $id): bool
    {
        return (bool) preg_match(self::NODE_ID_PATTERN, $id) && self::validPath($id);
    }

    /**
     * Whitelist a stored node map into the exact shape the emitter accepts,
     * dropping unknown ids, unknown flags and the disabled states below a
     * hidden node (a hidden subtree emits nothing). Returns null when nothing
     * survives, so the caller can forget the whole override.
     *
     * This is the security boundary for canvas nodes, the map-shaped sibling
     * of declarations(): only a whitelisted id, and only values with an exactly
     * known shape, may reach the stylesheet.
     */
    public static function cleanNodes(mixed $nodes): ?array
    {
        if (! is_array($nodes)) {
            return null;
        }

        $out = [];

        foreach ($nodes as $id => $node) {
            if (! is_string($id) || ! self::validNodeId($id) || ! is_array($node)) {
                continue;
            }

            $clean = ['props' => [], 'states' => [], 'breakpoints' => [], 'flags' => []];

            // Flags first, including `hidden`: a hidden node keeps its values in
            // storage (nodesCss withholds them from the emit instead), so
            // unhiding from the layer tree restores the exact styles it had —
            // wiping them here would make hide a one-way destructive action.
            foreach (self::NODE_FLAGS as $flag) {
                if (($node['flags'][$flag] ?? false) === true) {
                    $clean['flags'][$flag] = true;
                }
            }

            $props = self::validKeys((array) ($node['props'] ?? []));
            if ($props !== []) {
                $clean['props'] = $props;
            }

            foreach ((array) ($node['states'] ?? []) as $state => $row) {
                if (! isset(self::STATE_SELECTORS[$state]) || ! is_array($row)) {
                    continue;
                }

                $declarations = self::validKeys($row);

                if ($declarations !== []) {
                    $clean['states'][$state] = $declarations;
                }
            }

            foreach ((array) ($node['breakpoints'] ?? []) as $device => $row) {
                if (! isset(self::BREAKPOINTS[$device]) || ! is_array($row)) {
                    continue;
                }

                $declarations = self::validKeys($row);

                if ($declarations !== []) {
                    $clean['breakpoints'][$device] = $declarations;
                }
            }

            if ($clean['props'] === [] && $clean['states'] === [] && $clean['breakpoints'] === [] && $clean['flags'] === []) {
                continue;
            }

            $out[$id] = $clean;
        }

        return $out === [] ? null : $out;
    }

    /**
     * The stylesheet for a stored node map, ordered so the CSS cascade carries
     * every dimension without a server-side resolver:
     *
     *   local props  →  pseudo-class states  →  breakpoint overrides
     *
     * with breakpoints emitted narrow → wide (so a wider rule wins for the same
     * property) and hidden nodes emitting `display:none` — out of flow and
     * recoverable, never an `opacity:0` counterfeit.
     */
    public static function nodesCss(mixed $nodes): string
    {
        $nodes = self::cleanNodes($nodes);

        if ($nodes === null) {
            return '';
        }

        $body = '';

        foreach ($nodes as $id => $node) {
            $selector = '[data-studio-path="'.$id.'"]';
            $local = $node['props'] ?? [];

            // Hide is out-of-flow removal, recoverable from the tree: only
            // `display:none` reaches the page while the flag is set — local,
            // state and breakpoint values stay stored and resume the moment it
            // clears. Lock is editor-only and never reaches the public page,
            // so it emits nothing; a locked element stays fully interactive.
            if (($node['flags']['hidden'] ?? false) === true) {
                $body .= $selector.'{display:none;}';

                continue;
            }

            if ($local !== []) {
                $body .= $selector.'{'.self::toBody($local).'}';
            }

            foreach (self::STATE_SELECTORS as $state => $pseudo) {
                if (! isset($node['states'][$state])) {
                    continue;
                }

                $body .= $selector.$pseudo.'{'.self::toBody($node['states'][$state]).'}';
            }

            foreach (self::BREAKPOINT_ORDER as $device) {
                if (! isset($node['breakpoints'][$device])) {
                    continue;
                }

                $body .= '@media '.self::BREAKPOINTS[$device].'{'.$selector.'{'.self::toBody($node['breakpoints'][$device]).'}}';
            }
        }

        return $body;
    }

    /**
     * Render stored validKeys as declarations.
     *
     * @param  array<string, int|string>  $keys  stored shape (keys of KEYS)
     */
    protected static function toBody(array $keys): string
    {
        $body = '';

        foreach (self::declarations($keys) as $property => $value) {
            $body .= $property.':'.$value.';';
        }

        return $body;
    }
}
