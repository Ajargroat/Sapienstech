<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The crop component's render contract (TASK AC1, rework run 3).
 *
 * Run 2 deleted the @else full-image fallback — and with it the $url
 * assignment the <img> still consumed, so every path+valid-bbox question
 * threw "Undefined variable $url" on all five consumers. This pins the two
 * behaviours: a valid bbox renders the cropped storage image, a missing bbox
 * renders nothing (no whole-page fallback).
 */
class CroppedImageComponentTest extends TestCase
{
    public function test_a_valid_bbox_renders_the_storage_image(): void
    {
        $html = (string) $this->blade(<<<'BLADE'
            <x-cropped-image :path="'pages/q.jpg'"
                :bbox="['x'=>10,'y'=>5,'w'=>100,'h'=>50,'pw'=>800]" />
            BLADE);

        $this->assertStringContainsString('storage/pages/q.jpg', $html);
        $this->assertStringContainsString('<img', $html);
    }

    public function test_no_bbox_renders_no_image(): void
    {
        $html = (string) $this->blade('<x-cropped-image :path="\'pages/q.jpg\'" />');

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_zero_width_bbox_renders_no_image(): void
    {
        $html = (string) $this->blade(<<<'BLADE'
            <x-cropped-image :path="'pages/q.jpg'"
                :bbox="['x'=>10,'y'=>5,'w'=>0,'h'=>50,'pw'=>800]" />
            BLADE);

        $this->assertStringNotContainsString('<img', $html);
    }
}
