<?php

namespace Tests\Unit;

use App\Support\Merge;
use PHPUnit\Framework\TestCase;

class MergeTest extends TestCase
{
    public function test_associative_arrays_recurse(): void
    {
        $base     = ['theme' => ['colors' => ['primary' => '#000', 'text' => '#fff']]];
        $override = ['theme' => ['colors' => ['primary' => '#111']]];

        $this->assertSame(
            ['theme' => ['colors' => ['primary' => '#111', 'text' => '#fff']]],
            Merge::structural($base, $override)
        );
    }

    /**
     * The whole reason Merge exists instead of array_replace_recursive(): a
     * shorter list must replace, not splice itself into the longer one.
     */
    public function test_a_shorter_list_replaces_rather_than_merging_by_index(): void
    {
        $base     = ['sections' => ['hero', 'advisor', 'services', 'stats', 'blog', 'cta']];
        $override = ['sections' => ['hero', 'cta']];

        $this->assertSame(
            ['hero', 'cta'],
            Merge::structural($base, $override)['sections']
        );
    }

    /** Documents the bug we are avoiding, so nobody "simplifies" Merge away. */
    public function test_array_replace_recursive_would_have_leaked_baseline_sections(): void
    {
        $base     = ['sections' => ['hero', 'advisor', 'services', 'stats', 'blog', 'cta']];
        $override = ['sections' => ['hero', 'cta']];

        $this->assertCount(6, array_replace_recursive($base, $override)['sections']);
        $this->assertCount(2, Merge::structural($base, $override)['sections']);
    }

    public function test_new_keys_are_added(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2], Merge::structural(['a' => 1], ['b' => 2]));
    }

    /**
     * A layer must be able to blank a value out. ThemeTokens then reads an
     * explicit null as "derive this from the primitives", which is how a tenant
     * opts a hardcoded baseline token back into derivation.
     */
    public function test_null_overwrites_rather_than_being_ignored(): void
    {
        $merged = Merge::structural(
            ['colors' => ['primary' => '#000']],
            ['colors' => ['primary' => null]]
        );

        $this->assertNull($merged['colors']['primary']);
    }

    public function test_a_list_nested_deep_inside_assoc_arrays_still_replaces(): void
    {
        $base = ['public' => ['landing' => ['hero' => ['buttons' => [
            ['label' => 'a'], ['label' => 'b'], ['label' => 'c'],
        ]]]]];

        $override = ['public' => ['landing' => ['hero' => ['buttons' => [['label' => 'only']]]]]];

        $merged = Merge::structural($base, $override);

        $this->assertCount(1, $merged['public']['landing']['hero']['buttons']);
        $this->assertSame('only', $merged['public']['landing']['hero']['buttons'][0]['label']);
    }
}
