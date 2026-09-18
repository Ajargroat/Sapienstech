<?php

namespace App\Support;

class BlockStyles
{
    /**
     * Only bounded custom properties may reach the public markup, including
     * when blocks come from file-owned config rather than a studio request.
     */
    public static function variables(array $block): string
    {
        $styles = [];

        foreach (['background', 'color'] as $key) {
            $value = $block[$key] ?? null;

            if (is_string($value) && preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value)) {
                $styles[] = '--block-'.$key.':'.$value;
            }
        }

        foreach (['padding' => [0, 128, 'px'], 'radius' => [0, 128, 'px'], 'width' => [10, 100, '%']] as $key => [$min, $max, $unit]) {
            $value = $block[$key] ?? null;

            if ((is_int($value) || is_string($value))
                && preg_match('/\A[0-9]+\z/', (string) $value)
                && $value >= $min && $value <= $max) {
                $styles[] = '--block-'.$key.':'.(int) $value.$unit;
            }
        }

        return implode(';', $styles);
    }
}
