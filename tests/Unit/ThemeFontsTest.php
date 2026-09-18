<?php

namespace Tests\Unit;

use App\Support\ThemeFonts;
use App\Support\ThemeTokens;
use PHPUnit\Framework\TestCase;

class ThemeFontsTest extends TestCase
{
    public function test_default_variable_face_has_a_local_encoded_source(): void
    {
        $config = require dirname(__DIR__, 2).'/config/theme.php';
        $faces = ThemeFonts::faces($config['theme']['typography']);

        $this->assertCount(1, $faces);
        $this->assertSame('"Vazirmatn"', $faces[0]['family']);
        $this->assertSame('/fonts/vazirmatn/Vazirmatn%5Bwght%5D.woff2', $faces[0]['src']);
        $this->assertSame('100 900', $faces[0]['weight']);
        $this->assertSame('swap', $faces[0]['display']);
    }

    public function test_all_existing_font_roles_survive_token_resolution(): void
    {
        $theme = ThemeTokens::resolve(['typography' => [
            'font_family' => 'Body Font',
            'font_heading' => '"Heading Font", serif',
            'font_button' => 'Button Font',
            'font_accent' => 'Accent Font',
            'font_mono' => 'JetBrains Mono, ui-monospace, monospace',
        ]]);

        $this->assertSame([
            'font-body' => '"Body Font", sans-serif',
            'font-heading' => '"Heading Font", serif',
            'font-accent' => '"Accent Font", sans-serif',
            'font-button' => '"Button Font", sans-serif',
            'font-mono' => '"JetBrains Mono", ui-monospace, monospace',
        ], ThemeFonts::vars($theme['typography']));
    }

    public function test_null_roles_inherit_body_and_invalid_stacks_fall_back(): void
    {
        $vars = ThemeFonts::vars(['font_family' => 'فونت محلی', 'font_heading' => null, 'font_button' => 'Bad; font']);
        $this->assertSame('"فونت محلی", sans-serif', $vars['font-body']);
        $this->assertSame($vars['font-body'], $vars['font-heading']);
        $this->assertSame($vars['font-body'], $vars['font-button']);
        $this->assertSame('ui-monospace, monospace', $vars['font-mono']);
        foreach ([[], false, '', 'Bad<font>', 'Bad\\font', "Bad\nfont"] as $invalid) {
            $this->assertSame('"Vazirmatn", sans-serif', ThemeFonts::vars(['font_family' => $invalid])['font-body']);
        }
    }

    public function test_formats_static_weights_and_multiple_styles_are_supported(): void
    {
        foreach (['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'] as $extension => $format) {
            $face = ['family' => 'Local Font', 'src' => '/fonts/local/My Font.'.$extension, 'weight' => 700, 'style' => 'italic'];
            $faces = ThemeFonts::faces(['faces' => [$face, $face, array_merge($face, ['style' => 'normal'])]]);
            $this->assertCount(2, $faces);
            $this->assertSame($format, $faces[0]['format']);
            $this->assertSame('/fonts/local/My%20Font.'.$extension, $faces[0]['src']);
            $this->assertSame('700', $faces[0]['weight']);
            $this->assertSame('italic', $faces[0]['style']);
        }
    }

    public function test_unsafe_or_non_font_sources_are_skipped(): void
    {
        foreach ([
            'https://example.test/font.woff2', '//example.test/font.woff2',
            'data:font/woff2;base64,abc', '/other/font.woff2', 'fonts/font.woff2',
            '/fonts/../font.woff2', '/fonts/a/../../font.woff2', '/fonts//font.woff2',
            '/fonts/%2e%2e/font.woff2', '/fonts/a\\font.woff2',
            '/fonts/font.woff2?version=1', '/fonts/font.woff2#fragment',
            '/fonts/font.css', '/fonts/font.svg', '/fonts/"font.woff2',
            "/fonts/font\n.woff2", null, [],
        ] as $source) {
            $this->assertSame([], ThemeFonts::faces(['faces' => [['family' => 'Local', 'src' => $source]]]));
        }
    }

    public function test_invalid_descriptors_and_malformed_definitions_are_skipped(): void
    {
        $valid = ['family' => 'Local', 'src' => '/fonts/local.woff2'];
        foreach ([
            ['family' => 'Bad; font'], ['family' => []], ['family' => ''],
            ['weight' => '900 100'], ['weight' => '0'], ['weight' => '1001'],
            ['weight' => '400;'], ['weight' => []], ['style' => 'italic;'],
            ['display' => 'swap;'], ['format' => 'truetype'],
        ] as $invalid) {
            $this->assertSame([], ThemeFonts::faces(['faces' => [array_merge($valid, $invalid)]]));
        }
        $this->assertSame([], ThemeFonts::faces(['faces' => false]));
        $this->assertSame([], ThemeFonts::faces(['faces' => [null, 'invalid', []]]));
        $this->assertSame([], ThemeFonts::faces(['faces' => []]));
        $this->assertSame([], ThemeFonts::faces([]));
    }
}
