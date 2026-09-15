<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * The blog body's storage format: rich text HTML, filtered through a strict
 * allowlist on every save (and on render, for values written before this
 * existed or seeded straight into the DB).
 *
 * The shape mirrors what resources/js/features/blog-editor.js produces —
 * paragraphs, inline emphasis, lists, a couple of heading levels, one
 * text-align style per block, and image tags that may only point at the
 * current tenant's upload tree. Anything else is unwrapped (text kept) or
 * dropped outright, so a stored body can never carry script back into the
 * public page that echoes it unescaped.
 *
 * Bodies saved before the editor era are plain text; paragraphs() and
 * render() keep both worlds working without a backfill migration.
 */
class RichText
{
    /** Tag => attributes it may keep. Tags absent here are unwrapped. */
    private const ALLOWED = [
        'p' => ['style'], 'h2' => ['style'], 'h3' => ['style'], 'h4' => ['style'],
        'ul' => ['style'], 'ol' => ['style'], 'li' => [], 'blockquote' => ['style'],
        'pre' => [], 'br' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        's' => [], 'strike' => [], 'del' => [], 'ins' => [], 'sub' => [], 'sup' => [],
        'code' => [], 'mark' => [],
        'a' => ['href'], 'img' => ['src', 'alt', 'class'],
    ];

    /** Removed together with everything inside them. */
    private const DROP = [
        'script', 'style', 'noscript', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'textarea', 'select', 'svg', 'math', 'link',
        'meta', 'base', 'title', 'head', 'body', 'html',
    ];

    private const BLOCKS = [
        'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'div', 'pre', 'hr',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    private const IMAGE_ALIGN = ['align-left', 'align-center', 'align-right'];

    private const IMAGE_SIZE = ['size-small', 'size-medium', 'size-large', 'size-full'];

    /**
     * Non-ASCII codepoints are pushed through numeric entities before
     * libxml parses (it is byte-oriented and would mangle UTF-8), then
     * decoded again from the serialized result — html_entity_decode is not
     * used because it would also unescape the &lt; guards we rely on.
     */
    private const ENTITIES = [0x80, 0x10FFFF, 0, 0x1FFFFF];

    /**
     * Normalize an editor payload to allowlisted HTML. Plain text becomes
     * paragraphs so everything stored from now on is one format.
     */
    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! str_contains($html, '<')) {
            return self::paragraphs($html);
        }

        $encoded = mb_encode_numericentity($html, self::ENTITIES, 'UTF-8');

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $parsed = $dom->loadHTML(
            '<div>'.$encoded.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        if (! $parsed || ! ($root = $dom->documentElement)) {
            return self::paragraphs(strip_tags($html));
        }

        self::clean($root, false);
        self::foldRoot($root);

        $out = "";

        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim(mb_decode_numericentity($out, self::ENTITIES, 'UTF-8'));
    }

    /**
     * Display-safe body: HTML is re-sanitized (covers pre-editor rows and
     * seeded data), legacy plain text keeps its old nl2br treatment.
     */
    public static function render(?string $body): string
    {
        $body = trim((string) $body);

        if ($body === '') {
            return '';
        }

        return str_contains($body, '<') ? self::sanitize($body) : nl2br(e($body));
    }

    /**
     * Value for the editor's textarea prefill. Plain text is shown as the
     * paragraphs the editor will save, so legacy posts open WYSIWYG.
     */
    public static function editable(?string $body): string
    {
        $body = (string) $body;

        if ($body === '' || str_contains($body, '<')) {
            return $body;
        }

        return self::paragraphs($body);
    }

    /** Plain-text reading of a body (excerpts), with blocks folded to newlines. */
    public static function toPlainText(?string $body): string
    {
        $body = (string) $body;

        if (! str_contains($body, '<')) {
            return trim($body);
        }

        $text = preg_replace('#</(td|th)>#iu', ' ', $body);
        $text = preg_replace('#</(p|div|h[1-6]|li|blockquote|pre|figure|tr|table|tbody|thead)>#iu', "\n", (string) $text);
        $text = preg_replace('#<(?:br|hr)\s*/?>#iu', "\n", (string) $text);
        $text = strip_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[ \t\x{200B}]+/u', ' ', $text));
    }

    /** Escape each non-blank line into its own paragraph. */
    private static function paragraphs(string $plain): string
    {
        $lines = preg_split("/\r\n|\r|\n/", $plain) ?: [];

        return implode('', array_map(
            fn (string $line) => $line === '' ? '' : '<p>'.e(trim($line)).'</p>',
            $lines
        ));
    }

    /**
     * Depth-first allowlist pass. $inList tracks whether an li element at
     * this level is legal (only inside ul/ol — including nested ones).
     */
    private static function clean(DOMNode $parent, bool $inList): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                $parent->removeChild($node); // comments, processing instructions
                continue;
            }

            $tag = strtolower($node->nodeName);

            if (in_array($tag, self::DROP, true)) {
                $parent->removeChild($node);
                continue;
            }

            // Browsers and pasted documents lean on div wrappers for line
            // breaks: a wrapper of inline-only content becomes a paragraph,
            // one that already contains blocks is unwrapped so those become
            // siblings.
            if ($tag === 'div') {
                self::clean($node, $inList);

                $inParagraph = $parent instanceof DOMElement && strtolower($parent->nodeName) === 'p';

                if ($inParagraph || self::hasBlockChild($node)) {
                    self::unwrap($node);
                } else {
                    // Rebuild as <p> by hand: DOMDocument::renameNode() is not
                    // available in every PHP build (absent on Windows PHP 8.5),
                    // and a 500 on save is the worst possible failure mode here.
                    $p = $node->ownerDocument->createElement('p');

                    foreach ($node->attributes as $attr) {
                        $p->setAttribute($attr->nodeName, (string) $attr->nodeValue);
                    }

                    while ($node->firstChild) {
                        $p->appendChild($node->firstChild);
                    }

                    $node->parentNode->insertBefore($p, $node);
                    $node->parentNode->removeChild($node);
                    self::cleanAttributes($p);
                }

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::clean($node, $inList);
                self::unwrap($node);
                continue;
            }

            if (($tag === 'li' && ! $inList)
                || ($tag === 'p' && $parent instanceof DOMElement && strtolower($parent->nodeName) === 'p')) {
                self::clean($node, false);
                self::unwrap($node);
                continue;
            }

            if ($tag === 'img' && ! self::cleanAttributes($node)) {
                $parent->removeChild($node); // image from outside the tenant's tree
                continue;
            }

            self::cleanAttributes($node);
            self::clean($node, in_array($tag, ['ul', 'ol'], true));
        }
    }

    /**
     * Strip every attribute the allowlist does not mention. Returns false
     * when a mandatory value (an image src) is unusable and the element
     * should be dropped instead of kept empty.
     */
    private static function cleanAttributes(DOMElement $el): bool
    {
        $allowed = self::ALLOWED[strtolower($el->nodeName)] ?? [];

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($name);
                continue;
            }

            match ($name) {
                'style' => self::cleanStyle($el, $value),
                'href' => self::cleanHref($el, $value),
                'src' => self::cleanSrc($el, $value),
                'class' => self::cleanClass($el, $value),
                'colspan', 'rowspan' => self::cleanSpan($el, $name, $value),
                default => null,
            };
        }

        if (strtolower($el->nodeName) === 'img') {
            return $el->getAttribute('src') !== '';
        }

        return true;
    }

    /** Only a lone text-align survives — never free-form inline CSS. */
    private static function cleanStyle(DOMElement $el, string $value): void
    {
        if (preg_match('/^text-align:\s*(left|right|center|justify|start|end)\s*;?$/i', $value, $m)) {
            $el->setAttribute('style', 'text-align:'.strtolower($m[1]));

            return;
        }

        $el->removeAttribute('style');
    }

    private static function cleanHref(DOMElement $el, string $value): void
    {
        $href = trim($value);

        // Scheme-ish prefix must be an allowed one; backslashes and
        // protocol-relative forms are how browsers sneak hosts past checks.
        $blocked = $href === ''
            || str_contains($href, '\\')
            || str_starts_with($href, '//')
            || (preg_match('#^[\w.+-]+:#', $href) && ! preg_match('#^(https?|mailto|tel):#i', $href));

        if ($blocked) {
            $el->removeAttribute('href');

            return;
        }

        if (preg_match('#^https?://#i', $href)) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer');
        }

        $el->setAttribute('href', $href);
    }

    /** Inline images may only be the tenant's own uploads. */
    private static function cleanSrc(DOMElement $el, string $value): void
    {
        $slug = tenant()?->slug;
        $path = parse_url(trim($value), PHP_URL_PATH);

        $ok = $slug
            && is_string($path)
            && ! str_contains($path, '..')
            && str_starts_with(ltrim($path, '/'), "tenants/{$slug}/");

        if (! $ok) {
            $el->removeAttribute('src');

            return;
        }

        $el->setAttribute('src', trim($value));
    }

    /** Table cell spans are plain small integers — nothing else. */
    private static function cleanSpan(DOMElement $el, string $name, string $value): void
    {
        $value = trim($value);

        if (ctype_digit($value) && (int) $value >= 1 && (int) $value <= 100) {
            $el->setAttribute($name, $value);

            return;
        }

        $el->removeAttribute($name);
    }

    /**
     * Fold runs of loose inline content at the document root into
     * paragraphs, mirroring the editor's own normalization. Browsers render
     * root-level text as anonymous inline boxes with none of the paragraph
     * rhythm, so storage stays strictly block-structured.
     */
    private static function foldRoot(DOMNode $root): void
    {
        $run = [];

        foreach (iterator_to_array($root->childNodes) as $node) {
            $isBlock = $node instanceof DOMElement
                && in_array(strtolower($node->nodeName), self::BLOCKS, true);

            if ($isBlock) {
                self::foldRun($root, $run);
                $run = [];

                continue;
            }

            $run[] = $node;
        }

        self::foldRun($root, $run);
    }

    private static function foldRun(DOMNode $root, array $run): void
    {
        if ($run === []) {
            return;
        }

        $meaningful = array_values(array_filter(
            $run,
            fn (DOMNode $n) => ! ($n instanceof DOMText) || trim($n->nodeValue) !== '',
        ));

        if ($meaningful === []) {
            return;
        }

        $p = $root->ownerDocument->createElement('p');
        $root->insertBefore($p, $run[0]);

        foreach ($meaningful as $node) {
            $p->appendChild($node);
        }

        foreach ($run as $node) {
            if ($node->parentNode === $root) {
                $root->removeChild($node); // whitespace between the folded nodes
            }
        }
    }

    /** Only the editor's own align/size vocabulary is honored on images. */
    private static function cleanClass(DOMElement $el, string $value): void
    {
        $kept = array_values(array_intersect(
            preg_split('/\s+/', trim($value)) ?: [],
            [...self::IMAGE_ALIGN, ...self::IMAGE_SIZE]
        ));

        if ($kept === []) {
            $el->removeAttribute('class');

            return;
        }

        // At most one align and one size token, in a fixed order, so the
        // stored markup is stable across saves.
        $align = array_intersect($kept, self::IMAGE_ALIGN);
        $size = array_intersect($kept, self::IMAGE_SIZE);

        $el->setAttribute('class', implode(' ', array_merge($align, $size)));
    }

    private static function hasBlockChild(DOMElement $el): bool
    {
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::BLOCKS, true)) {
                return true;
            }
        }

        return false;
    }

    /** Replace an element with its (already cleaned) children. */
    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;

        if (! $parent) {
            return;
        }

        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }
}
