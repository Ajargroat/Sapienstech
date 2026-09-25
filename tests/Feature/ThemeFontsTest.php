<?php

namespace Tests\Feature;

use App\Support\StudioSchema;
use App\Support\ThemeFonts;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ThemeFontsTest extends TestCase
{
    private function withFontDirectory(callable $test): void
    {
        $original = public_path();
        $temporary = sys_get_temp_dir().'/studio-fonts-'.bin2hex(random_bytes(8));
        mkdir($temporary.'/fonts', 0777, true);
        $this->app->usePublicPath($temporary);
        try {
            $test($temporary.'/fonts');
        } finally {
            $this->app->usePublicPath($original);
            File::deleteDirectory($temporary);
        }
    }

    public function test_directory_choices_track_disk_changes_and_reject_unusable_directories(): void
    {
        $this->withFontDirectory(function ($root) {
            $this->assertSame(['' => 'Theme default (reset)'], ThemeFonts::choices([]));
            foreach (['installed', 'empty', 'unknown', 'bad.name'] as $directory) {
                mkdir($root.'/'.$directory);
            }
            file_put_contents($root.'/installed/Example-BoldItalic.woff2', 'fixture');
            file_put_contents($root.'/unknown/Example.woff2', 'fixture');
            file_put_contents($root.'/unknown/Example[wght].woff2', 'fixture');
            file_put_contents($root.'/bad.name/Example-Regular.ttf', 'fixture');
            file_put_contents($root.'/empty/readme.txt', 'not a font');
            $this->assertSame(['', 'installed'], array_keys(ThemeFonts::choices([])));

            site_override(['theme' => ['typography' => ['font_family' => 'Legacy Body, serif', 'faces' => []]]]);
            $path = 'theme.typography.font_family';
            $validates = static fn ($value) => Validator::make(
                ['theme' => ['typography' => ['font_family' => $value]]],
                [$path => StudioSchema::rules()[$path]],
            )->passes();
            $this->assertTrue($validates('installed'));
            $this->assertTrue($validates('Legacy Body, serif'));
            $this->assertTrue($validates(''));
            $this->assertSame([$path => null], StudioSchema::normalize([$path => '']));
            foreach (['Other Font', '../installed', '/fonts/installed', 'installed, serif', 'bad.name', 'empty', 'unknown', ['installed']] as $invalid) {
                $this->assertFalse($validates($invalid));
            }

            unlink($root.'/installed/Example-BoldItalic.woff2');
            clearstatcache();
            $this->assertFalse($validates('installed'));
            $this->assertArrayNotHasKey('installed', ThemeFonts::choices([]));
            file_put_contents($root.'/installed/Example-Regular.woff', 'fixture');
            $this->assertTrue($validates('installed'));
            File::deleteDirectory($root.'/installed');
            clearstatcache();
            $this->assertFalse($validates('installed'));
        });
    }

    public function test_each_role_loads_its_directory_alias_and_font_changes_reload_preview(): void
    {
        $this->withFontDirectory(function ($root) {
            $typography = ['faces' => []];
            foreach (['body' => 'family', 'heading' => 'heading', 'accent' => 'accent', 'button' => 'button', 'mono' => 'mono'] as $role => $key) {
                mkdir($root.'/'.$role);
                file_put_contents($root.'/'.$role.'/Example-Regular.woff2', 'fixture');
                $typography['font_'.$key] = $role;
                $this->assertSame('reload', StudioSchema::liveMode('theme.typography.font_'.$key));
            }
            $html = view('partials.theme-fonts', ['fontTheme' => ['typography' => $typography]])->render();
            $this->assertSame(5, substr_count($html, '@font-face'));
            foreach (['body', 'heading', 'accent', 'button', 'mono'] as $role) {
                $this->assertStringContainsString('--font-'.$role.': "StudioFont-'.$role.'",', $html);
                $this->assertStringContainsString('font-family: "StudioFont-'.$role.'";', $html);
                $this->assertStringContainsString('url("/fonts/'.$role.'/Example-Regular.woff2")', $html);
            }
            $this->assertSame('token', StudioSchema::liveMode('theme.colors.primary'));
            $typography['font_heading'] = 'body';
            $this->assertCount(4, ThemeFonts::faces($typography));
        });
    }

    public function test_descriptors_are_inferred_only_when_known_and_explicit_metadata_wins(): void
    {
        $this->withFontDirectory(function ($root) {
            mkdir($root.'/local');
            foreach (['Example-BoldItalic.woff2', 'Example-Light.ttf', 'Example[wght].woff2', 'Mystery.otf'] as $filename) {
                file_put_contents($root.'/local/'.$filename, 'fixture');
            }
            $typography = ['font_accent' => 'local', 'faces' => []];
            $faces = ThemeFonts::faces($typography);
            $this->assertCount(2, $faces);
            $this->assertSame(['700', '300'], array_column($faces, 'weight'));
            $this->assertSame(['italic', 'normal'], array_column($faces, 'style'));

            $typography['faces'] = [
                ['family' => 'Real Family', 'src' => '/fonts/local/Example[wght].woff2', 'weight' => '200 800'],
                ['family' => 'Real Family', 'src' => '/fonts/local/Mystery.otf', 'weight' => '600', 'style' => 'oblique'],
                ['family' => 'Real Family', 'src' => '/fonts/local/Example-BoldItalic.woff2', 'weight' => '750', 'style' => 'italic'],
            ];
            $faces = ThemeFonts::faces($typography);
            $aliases = array_values(array_filter($faces, static fn ($face) => $face['family'] === '"StudioFont-local"'));
            $this->assertCount(7, $faces);
            $this->assertCount(4, $aliases);
            $this->assertSame(['200 800', '600', '750', '300'], array_column($aliases, 'weight'));
            $this->assertSame('/fonts/local/Example%5Bwght%5D.woff2', $aliases[0]['src']);
            $this->assertSame('"Real Family"', $faces[0]['family']);
        });
    }

    public function test_font_picker_has_directory_radios_and_retains_legacy_or_missing_values(): void
    {
        $this->withFontDirectory(function ($root) {
            mkdir($root.'/installed');
            file_put_contents($root.'/installed/Example-Regular.woff2', 'fixture');
            foreach (['family', 'heading', 'accent', 'button', 'mono'] as $role) {
                $path = 'theme.typography.font_'.$role;
                foreach (['Legacy Font, serif', 'removed-folder', 'installed'] as $current) {
                    $resolved = ['theme' => ['typography' => ['font_'.$role => $current, 'faces' => []]]];
                    $html = view('consultant.settings.partials._field', [
                        'field' => StudioSchema::field($path), 'resolved' => $resolved, 'overrides' => [],
                        'errors' => new ViewErrorBag,
                    ])->render();
                    $this->assertStringNotContainsString('type="text"', $html);
                    $this->assertStringContainsString('type="radio"', $html);
                    $this->assertStringContainsString('value="installed"', $html);
                    $this->assertStringContainsString('value=""', $html);
                    $this->assertMatchesRegularExpression('/value="'.preg_quote($current, '/').'"\s+checked/', $html);
                    $this->assertStringContainsString('data-live="reload"', $html);
                    $this->assertSame([], StudioSchema::diff(StudioSchema::normalize([$path => $current]), $resolved));
                }
            }
        });
    }

    public function test_shared_partial_renders_local_faces_and_each_font_role_once(): void
    {
        site_override(['theme' => ['typography' => [
            'font_family' => 'Local Body',
            'font_heading' => 'Local Heading, serif',
            'font_button' => 'Local Button',
            'faces' => [
                ['family' => 'Local Body', 'src' => '/fonts/body/Body[wght].woff2', 'weight' => '100 900'],
                ['family' => 'Local Heading', 'src' => '/fonts/heading.woff', 'weight' => '700'],
                ['family' => 'Local Button', 'src' => '/fonts/button.ttf'],
            ],
        ]]]);

        $html = view('partials.theme-vars')->render();
        $this->assertSame(1, substr_count($html, 'data-theme-fonts'));
        $this->assertSame(3, substr_count($html, '@font-face'));
        $this->assertStringContainsString('url("/fonts/body/Body%5Bwght%5D.woff2") format("woff2")', $html);
        $this->assertStringContainsString('--font-body: "Local Body", sans-serif;', $html);
        $this->assertStringContainsString('--font-heading: "Local Heading", serif;', $html);
        $this->assertStringContainsString('--font-button: "Local Button", sans-serif;', $html);
        foreach (['body', 'heading', 'button', 'accent', 'mono'] as $role) {
            $this->assertSame(1, substr_count($html, '--font-'.$role.':'));
        }
        $this->assertStringNotContainsString('&quot;', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('--c-primary:', $html);
    }

    public function test_empty_faces_disable_loading_and_legacy_public_mode_only_emits_fonts(): void
    {
        site_override(['theme' => ['typography' => ['faces' => [], 'font_family' => 'system-ui']]]);
        $html = view('partials.theme-vars', ['fontsOnly' => true])->render();

        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringContainsString('--font-body: system-ui;', $html);
        $this->assertStringNotContainsString('--c-primary:', $html);
    }

    public function test_active_shells_load_fonts_only_through_the_shared_partial(): void
    {
        foreach ([
            'layouts/consultant', 'layouts/student', 'layouts/teacher', 'layouts/public',
            'Public/landing', 'Public/blog/layout', 'auth/login',
        ] as $view) {
            $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertSame(1, substr_count($source, "@include('partials.theme-vars'"), $view);
            $this->assertStringNotContainsString('font_url', $source, $view);
            $this->assertStringNotContainsString('fonts.googleapis.com', $source, $view);
            $this->assertStringNotContainsString('fonts.gstatic.com', $source, $view);
            $this->assertStringNotContainsString("@include('partials.theme-fonts'", $source, $view);
        }
    }
}
