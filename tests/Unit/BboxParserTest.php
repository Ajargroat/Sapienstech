<?php

namespace Tests\Unit;

use App\Support\BboxParser;
use PHPUnit\Framework\TestCase;

/**
 * The source frame was verified against real Supabase rows and their page
 * images (the crop rectangles land on the diagrams): `[ymin, xmin, ymax,
 * xmax]` with both axes normalized to 0–1000 of the page's own size. Every
 * number below mirrors a row that was checked visually.
 */
class BboxParserTest extends TestCase
{
    private const PAGE = [1408, 1988]; // the bank's page size

    public function test_source_frame_scales_each_axis_to_the_page(): void
    {
        // questions…-q8, raw [351,64,442,271] → the ECG figure on page 063995be…
        $box = BboxParser::parse('[351,64,442,271]', self::PAGE);

        $this->assertSame([
            'x' => 90, 'y' => 698, 'w' => 292, 'h' => 181, 'pw' => 1408, 'ph' => 1988,
        ], $box);
    }

    public function test_source_frame_accepts_the_float_format(): void
    {
        // has_diagram row, raw [528.0, 88.0, 621.0, 520.0]
        $box = BboxParser::parse('[528.0,88.0,621.0,520.0]', self::PAGE);

        $this->assertSame(['x' => 124, 'y' => 1050, 'w' => 608, 'h' => 185, 'pw' => 1408, 'ph' => 1988], $box);
    }

    public function test_source_frame_is_the_default(): void
    {
        $this->assertSame(BboxParser::parse('[351,64,442,271]', self::PAGE), BboxParser::parse('[351,64,442,271]', self::PAGE, 'yxxy'));
    }

    public function test_pixel_frames_are_explicitly_opt_in(): void
    {
        $page = [1000, 2000];

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 200, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('[10,20,110,220]', $page, 'corners')
        );

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 200, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('[10,20,100,200]', $page, 'xywh')
        );
    }

    /** The run-1 heuristic, unchanged, for rows that arrive in page pixels. */
    public function test_auto_keeps_the_legacy_corners_xywh_guess(): void
    {
        $page = [1000, 2000];

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 200, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('[10,20,110,220]', $page, 'auto')
        );

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 5, 'h' => 500, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('[10,20,5,500]', $page, 'auto')
        );
    }

    public function test_box_is_clamped_to_the_page(): void
    {
        $box = BboxParser::parse('[0,0,1200,1200]', [1000, 1000]);

        $this->assertSame(['x' => 0, 'y' => 0, 'w' => 1000, 'h' => 1000, 'pw' => 1000, 'ph' => 1000], $box);
    }

    public function test_degenerate_and_outside_boxes_are_refused(): void
    {
        // Zero-sized region.
        $this->assertNull(BboxParser::parse('[100,100,100,100]', self::PAGE));

        // Wholly outside the page: clamps to nothing rather than a wrong crop.
        $this->assertNull(BboxParser::parse('[-500,-500,-100,-100]', self::PAGE));
    }

    public function test_a_missing_page_size_or_bbox_is_null(): void
    {
        $this->assertNull(BboxParser::parse('[351,64,442,271]', null));
        $this->assertNull(BboxParser::parse('[351,64,442,271]', [0, 1988]));
        $this->assertNull(BboxParser::parse('', self::PAGE));
        $this->assertNull(BboxParser::parse('not json', self::PAGE));
        $this->assertNull(BboxParser::parse('[1,2,3]', self::PAGE));
    }

    public function test_unknown_frame_is_refused(): void
    {
        $this->assertNull(BboxParser::parse('[10,20,110,220]', self::PAGE, 'nope'));
    }

    public function test_associative_boxes_stay_in_page_pixels(): void
    {
        $page = [1000, 2000];

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 100, 'h' => 200, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('{"x":10,"y":20,"width":100,"height":200}', $page)
        );

        $this->assertSame(
            ['x' => 10, 'y' => 20, 'w' => 90, 'h' => 180, 'pw' => 1000, 'ph' => 2000],
            BboxParser::parse('{"left":10,"top":20,"right":100,"bottom":200}', $page)
        );

        // No origin at all: nothing to crop.
        $this->assertNull(BboxParser::parse('{"width":100,"height":200}', $page));
    }
}
