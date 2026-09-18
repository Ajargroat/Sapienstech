<?php

namespace Tests\Feature;

use App\Support\ThemeIcons;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ThemeIconsTest extends TestCase
{
    public function test_catalog_has_four_custom_sets_and_helper_has_five_choices(): void
    {
        $this->assertSame(['lucide', 'tabler', 'bi', 'la'], array_keys(ThemeIcons::sets()));
        $this->assertCount(5, ThemeIcons::choices());
        $this->assertSame(['font-awesome', 'lucide', 'tabler', 'bi', 'la'], array_keys(ThemeIcons::choices()));
    }

    public function test_every_catalog_concept_and_alias_has_a_generated_mapping_and_preview(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('icons/catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $manifest = json_decode(file_get_contents(public_path('icons/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $names = array_merge(array_keys($catalog['icons']), array_keys($catalog['aliases']));

        $this->assertSame(array_keys(ThemeIcons::sets()), array_keys($manifest));
        foreach (ThemeIcons::sets() as $set => $label) {
            $this->assertSame([], $manifest[$set]['fallback'], $set);
            $this->assertSame(count($names), $manifest[$set]['mapped'], $set);
            $this->assertCount(count($names), $manifest[$set]['icons'], $set);
            foreach ($catalog['icons'] as $concept => $mappings) {
                $this->assertArrayHasKey($set, $mappings, "Missing mapping: {$concept} [{$set}]");
                $this->assertNotEmpty($mappings[$set], "Missing mapping: {$concept} [{$set}]");
                $this->assertSame($mappings[$set], $manifest[$set]['icons'][$concept], "{$concept} [{$set}]");
            }
            foreach ($catalog['aliases'] as $alias => $concept) {
                $this->assertArrayHasKey($concept, $catalog['icons'], "Invalid alias: {$alias}");
                $this->assertSame($manifest[$set]['icons'][$concept], $manifest[$set]['icons'][$alias]);
            }
            $css = file_get_contents(public_path("icons/{$set}.css"));
            $this->assertStringContainsString(':is(.fa,.fas,.far,.fa-solid,.fa-regular)', $css);
            $this->assertStringContainsString(':not(.fab):not(.fa-brands)', $css);
            foreach (ThemeIcons::preview($set, $names) as $preview) {
                $this->assertSame($set, $preview['set']);
                $this->assertIsString($preview['dataUri'], "Missing preview: {$preview['name']} [{$set}]");
                $this->assertStringStartsWith('data:image/svg+xml,', $preview['dataUri']);
                $this->assertStringContainsString('currentColor', rawurldecode($preview['dataUri']));
                $this->assertStringNotContainsString('#94a3b8', rawurldecode($preview['dataUri']));
                $this->assertStringContainsString('.fa-'.$preview['name'].':not(.fab)', $css);
            }
        }
    }

    public function test_generated_icons_preserve_native_coordinates_and_share_a_centered_em_box(): void
    {
        $manifest = json_decode(file_get_contents(public_path('icons/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (ThemeIcons::sets() as $set => $label) {
            $upstream = json_decode(file_get_contents(base_path("node_modules/@iconify-json/{$set}/icons.json")), true, 512, JSON_THROW_ON_ERROR);
            $css = file_get_contents(public_path("icons/{$set}.css"));
            foreach (ThemeIcons::preview($set, array_keys($manifest[$set]['icons'])) as $preview) {
                $icon = $upstream['icons'][$manifest[$set]['icons'][$preview['name']]];
                // Iconify defaults to 16, even when the set omits its dimensions.
                $box = implode(' ', [
                    $icon['left'] ?? $upstream['left'] ?? 0,
                    $icon['top'] ?? $upstream['top'] ?? 0,
                    $icon['width'] ?? $upstream['width'] ?? 16,
                    $icon['height'] ?? $upstream['height'] ?? 16,
                ]);
                $svg = rawurldecode($preview['dataUri']);
                $this->assertStringContainsString('viewBox="'.$box.'"', $svg, $set.':'.$preview['name']);
                $this->assertStringContainsString('width="24" height="24"', $svg);
                $this->assertStringContainsString($preview['dataUri'], $css, 'Picker and site must render identical geometry');
            }
            $this->assertStringContainsString('width:1em;height:1em;vertical-align:-.125em;', $css);
            $this->assertStringContainsString('center/contain no-repeat', $css);
        }
        $this->assertSame('check-lg', $manifest['bi']['icons']['check']);
        $this->assertSame('plus-lg', $manifest['bi']['icons']['plus']);
        $this->assertSame('x-lg', $manifest['bi']['icons']['xmark']);
        $this->assertSame('x-lg', $manifest['bi']['icons']['times']);
    }

    public function test_semantic_aliases_preserve_warning_and_circled_close_meaning(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('icons/catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('circle-exclamation', $catalog['aliases']['exclamation-circle']);
        $this->assertSame('circle-xmark', $catalog['aliases']['times-circle']);
        $this->assertSame('times', $catalog['icons']['xmark']['la']);
        $this->assertSame('circle-question-mark', $catalog['icons']['circle-question']['lucide']);
    }

    public function test_node_guard_rejects_new_used_concepts_and_missing_mappings_in_every_set(): void
    {
        $process = new Process(['node', 'scripts/audit-icon-usage.mjs', '--self-test'], base_path());
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('Icon guard regression tests passed', $process->getOutput());
        $this->assertStringContainsString('Icon audit passed', $process->getOutput());
    }

    public function test_unknown_or_missing_set_falls_back_to_font_awesome(): void
    {
        $this->assertSame('font-awesome', ThemeIcons::selected('not-a-set'));
        $this->assertSame('font-awesome', ThemeIcons::selected(null));
        $this->assertSame('lucide', ThemeIcons::selected('lucide'));
        $this->assertSame('tabler', ThemeIcons::selected('tabler'));
        $this->assertSame('font-awesome', ThemeIcons::selected('feather'));
        $this->assertSame('font-awesome', ThemeIcons::selected('heroicons'));
    }

    /** Every entry point shares one loader and never emits the hard-wired link. */
    public function test_layouts_load_icons_through_the_shared_partial_only(): void
    {
        foreach ([
            'layouts/consultant', 'layouts/student', 'layouts/teacher',
            'Public/landing', 'Public/blog/layout', 'auth/login', 'layouts/public',
        ] as $view) {
            $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertSame(1, substr_count($source, "@include('partials.theme-vars'"), $view);
            $this->assertStringNotContainsString('icon_library_url', $source, $view);
            $this->assertStringNotContainsString('cdnjs.cloudflare.com', $source, $view);
            $this->assertStringNotContainsString("@include('partials.theme-icons'", $source, $view);
        }
    }

    public function test_shared_partial_loads_selected_set_and_keeps_font_awesome_fallback(): void
    {
        site_override(['theme' => ['icons' => ['set' => 'tabler']]]);

        $html = view('partials.theme-vars')->render();

        $this->assertSame(1, substr_count($html, 'data-icon-set="tabler"'));
        $this->assertSame(1, substr_count($html, 'icons/tabler.css'));
        $this->assertSame(1, substr_count($html, 'font-awesome/6.4.0'));
        $this->assertStringNotContainsString('icons/lucide.css', $html);
    }

    public function test_font_awesome_choice_skips_the_override_stylesheet(): void
    {
        site_override(['theme' => ['icons' => ['set' => 'font-awesome']]]);

        $html = view('partials.theme-vars')->render();

        $this->assertSame(1, substr_count($html, 'font-awesome/6.4.0'));
        $this->assertStringNotContainsString('data-icon-set', $html);
    }
}
