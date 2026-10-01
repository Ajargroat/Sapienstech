<?php

namespace App\Support;

/**
 * Normalizes the raw Supabase `questions.diagram_bbox` into the crop rect the
 * browser frames with pure CSS: {"x","y","w","h"} in page pixels plus
 * {"pw","ph"} page size (see components/cropped-image.blade.php).
 *
 * The source frame was established empirically against real rows (the pages
 * are 1408x1988): the stored value is `[ymin, xmin, ymax, xmax]` with BOTH
 * axes normalized to 0–1000 of the page's own size — so x = xmin/1000*pw and
 * y = ymin/1000*ph. Reading those numbers as raw pixels (the run-1 behaviour)
 * is what put every crop in the wrong place.
 *
 * Boxes that end up degenerate or outside the page are refused (null), never
 * clamped into a wrong crop: a question with no picture must render no
 * picture. The pixel-space `corners`/`xywh`/`auto` frames are kept for rows
 * that arrive in page pixels.
 */
class BboxParser
{
    /** Every axis of the source bbox is scaled by 1/1000 of its own page size. */
    private const SCALE = 1000;

    /**
     * @param  ?array  $pageSize  [width, height] of the page image
     * @return array{x:int,y:int,w:int,h:int,pw:int,ph:int}|null
     */
    public static function parse(?string $raw, ?array $pageSize, string $frame = 'yxxy'): ?array
    {
        $pw = (int) ($pageSize[0] ?? 0);
        $ph = (int) ($pageSize[1] ?? 0);

        // The component needs pw > 0 to draw the crop at all.
        if ($pw <= 0 || $ph <= 0) {
            return null;
        }

        $box = json_decode((string) $raw, true);

        if (! is_array($box)) {
            return null;
        }

        if (array_is_list($box) && count($box) === 4) {
            $rect = self::rectFromList(array_map('floatval', $box), $frame, $pw, $ph);
        } else {
            $rect = self::rectFromAssoc($box);
        }

        if ($rect === null) {
            return null;
        }

        [$x1, $y1, $x2, $y2] = $rect;

        // Clamp into the page, then refuse anything that degenerated: float
        // boxes routinely round ~1px over pw/ph, but a box wholly outside the
        // page has no crop to show.
        $x1 = max(0.0, min((float) $pw, $x1));
        $x2 = max(0.0, min((float) $pw, $x2));
        $y1 = max(0.0, min((float) $ph, $y1));
        $y2 = max(0.0, min((float) $ph, $y2));

        $x = (int) round($x1);
        $y = (int) round($y1);
        $w = (int) round($x2) - $x;
        $h = (int) round($y2) - $y;

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'pw' => $pw, 'ph' => $ph];
    }

    /**
     * A list of four numbers in one of the known orders → [x1, y1, x2, y2]
     * page pixels, or null when the frame cannot describe the data.
     *
     * @return array{float,float,float,float}|null
     */
    private static function rectFromList(array $v, string $frame, int $pw, int $ph): ?array
    {
        [$a, $b, $c, $d] = $v;

        switch ($frame) {
            case 'yxxy':      // [ymin, xmin, ymax, xmax] normalized 0..1000
                return [$b / self::SCALE * $pw, $a / self::SCALE * $ph,
                    $d / self::SCALE * $pw, $c / self::SCALE * $ph];

            case 'corners':   // [x1, y1, x2, y2] in page pixels
                return [$a, $b, $c, $d];

            case 'xywh':      // [x, y, w, h] in page pixels
                return [$a, $b, $a + $c, $b + $d];

            case 'auto':      // legacy guess, kept only for old pixel-space rows
                return ($c > $a && $d > $b) ? [$a, $b, $c, $d] : [$a, $b, $a + $c, $b + $d];
        }

        return null;
    }

    /**
     * An associative box ({"x","y","width",…} or {"left","top",…}) — the
     * run-1 shape, always in page pixels.
     *
     * @return array{float,float,float,float}|null
     */
    private static function rectFromAssoc(array $box): ?array
    {
        $x = (float) ($box['x'] ?? $box['left'] ?? NAN);
        $y = (float) ($box['y'] ?? $box['top'] ?? NAN);

        if (is_nan($x) || is_nan($y)) {
            return null;
        }

        $w = (float) ($box['width'] ?? $box['w'] ?? (isset($box['right']) ? $box['right'] - $x : NAN));
        $h = (float) ($box['height'] ?? $box['h'] ?? (isset($box['bottom']) ? $box['bottom'] - $y : NAN));

        if (is_nan($w) || is_nan($h)) {
            return null;
        }

        return [$x, $y, $x + $w, $y + $h];
    }
}
