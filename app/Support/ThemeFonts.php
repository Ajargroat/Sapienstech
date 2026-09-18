<?php

namespace App\Support;

final class ThemeFonts
{
    private const FORMATS = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    private const GENERICS = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded'];

    /** Safe CSS font stacks, using the same role names as ThemeTokens. */
    public static function vars(array $typography): array
    {
        $catalog = self::catalog($typography);
        $resolve = static fn ($value, $fallback) => is_string($value) && isset($catalog[$value])
            ? '"'.$catalog[$value]['family'].'", '.$fallback
            : self::stack($value, $fallback);
        $body = $resolve($typography['font_family'] ?? null, '"Vazirmatn", sans-serif');
        $vars = ['font-body' => $body];
        foreach (['heading', 'accent', 'button', 'mono'] as $role) {
            $vars['font-'.$role] = $resolve(
                $typography['font_'.$role] ?? null,
                $role === 'mono' ? 'ui-monospace, monospace' : $body,
            );
        }

        return $vars;
    }

    /** Keep explicit faces and add aliases for every selected directory role. */
    public static function faces(array $typography): array
    {
        $faces = self::configuredFaces($typography);
        $catalog = self::catalog($typography);
        foreach (['family', 'heading', 'accent', 'button', 'mono'] as $role) {
            $directory = $typography['font_'.$role] ?? null;
            if (!is_string($directory) || !isset($catalog[$directory])) {
                continue;
            }
            foreach ($catalog[$directory]['faces'] as $face) {
                if (!in_array($face, $faces, true)) {
                    $faces[] = $face;
                }
            }
        }

        return $faces;
    }

    /** Runtime choices, plus an explicit reset and an unchanged legacy value. */
    public static function choices(array $typography, mixed $current = null): array
    {
        $choices = ['' => 'پیش‌فرض تم (بازنشانی)'];
        foreach (self::catalog($typography) as $directory => $font) {
            $choices[$directory] = $directory;
        }
        if (is_string($current) && $current !== '' && !array_key_exists($current, $choices)) {
            $choices[$current] = 'حفظ مقدار فعلی: '.$current;
        }

        return $choices;
    }

    /**
     * Only immediate, safe directories with usable local faces are selectable.
     * No static/config cache: deployment changes must appear in the next request.
     */
    private static function catalog(array $typography): array
    {
        $public = app() instanceof \Illuminate\Foundation\Application
            ? public_path()
            : dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'public';
        $root = $public.DIRECTORY_SEPARATOR.'fonts';
        $catalog = [];
        $configured = self::configuredFaces($typography);
        foreach (is_dir($root) ? (scandir($root) ?: []) : [] as $directory) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/', $directory) !== 1) {
                continue;
            }
            $folder = $root.DIRECTORY_SEPARATOR.$directory;
            if (!is_dir($folder) || is_link($folder)) {
                continue;
            }
            $family = 'StudioFont-'.$directory;
            $faces = [];
            // Explicit metadata wins over filename inference, including variable
            // ranges and nonstandard filenames. Preserve its original family too.
            foreach ($configured as $face) {
                $url = rawurldecode($face['src']);
                if (!str_starts_with($url, '/fonts/'.$directory.'/')) {
                    continue;
                }
                $file = $public.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, ltrim($url, '/'));
                if (self::localFile($file, $folder)) {
                    $faces[] = array_replace($face, ['family' => '"'.$family.'"']);
                }
            }
            foreach (scandir($folder) ?: [] as $filename) {
                $file = $folder.DIRECTORY_SEPARATOR.$filename;
                $source = self::source('/fonts/'.$directory.'/'.$filename);
                if ($source === null || !self::localFile($file, $folder) || in_array($source['src'], array_column($faces, 'src'), true)) {
                    continue;
                }
                $descriptors = self::filenameDescriptors(pathinfo($filename, PATHINFO_FILENAME));
                if ($descriptors === null) {
                    continue;
                }
                $faces[] = ['family' => '"'.$family.'"'] + $source + $descriptors + ['display' => 'swap'];
            }
            if ($faces !== []) {
                $catalog[$directory] = ['family' => $family, 'faces' => array_values($faces)];
            }
        }

        return $catalog;
    }

    private static function localFile(string $file, string $folder): bool
    {
        $real = realpath($file);
        $base = realpath($folder);

        return $real !== false && $base !== false && is_file($file) && is_readable($file)
            && !is_link($file) && str_starts_with($real, $base.DIRECTORY_SEPARATOR);
    }

    private static function filenameDescriptors(string $stem): ?array
    {
        // A bare family name or [wght] does not tell us the weight/range.
        // These files require explicit faces metadata rather than guessing 400.
        if (preg_match('/(?:\A|[-_ ])(Thin|ExtraLight|UltraLight|Light|Regular|Normal|Medium|SemiBold|DemiBold|Bold|ExtraBold|UltraBold|Black|Heavy|[1-9]00|1000)(?:[-_ ]?(Italic|Oblique))?\z/i', $stem, $matches) === 1) {
            $weights = ['thin' => '100', 'extralight' => '200', 'ultralight' => '200', 'light' => '300', 'regular' => '400', 'normal' => '400', 'medium' => '500', 'semibold' => '600', 'demibold' => '600', 'bold' => '700', 'extrabold' => '800', 'ultrabold' => '800', 'black' => '900', 'heavy' => '900'];

            return ['weight' => $weights[strtolower($matches[1])] ?? $matches[1], 'style' => strtolower($matches[2] ?? 'normal')];
        }
        if (preg_match('/(?:\A|[-_ ])(Italic|Oblique)\z/i', $stem, $matches) === 1) {
            return ['weight' => '400', 'style' => strtolower($matches[1])];
        }

        return null;
    }

    /** Invalid explicit faces are skipped, retaining the legacy local URL contract. */
    private static function configuredFaces(array $typography): array
    {
        $faces = [];
        $definitions = $typography['faces'] ?? [];
        if (!is_array($definitions)) {
            return [];
        }

        foreach ($definitions as $face) {
            if (!is_array($face) || !self::validName($face['family'] ?? null)) {
                continue;
            }
            $source = self::source($face['src'] ?? null);
            $weight = self::weight($face['weight'] ?? '400');
            $style = $face['style'] ?? 'normal';
            $display = $face['display'] ?? 'swap';
            if ($source === null || $weight === null
                || !in_array($style, ['normal', 'italic', 'oblique'], true)
                || !in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true)
                || (isset($face['format']) && $face['format'] !== $source['format'])) {
                continue;
            }
            $normalized = [
                'family' => '"'.trim($face['family']).'"',
                'src' => $source['src'],
                'format' => $source['format'],
                'weight' => $weight,
                'style' => $style,
                'display' => $display,
            ];
            if (!in_array($normalized, $faces, true)) {
                $faces[] = $normalized;
            }
        }

        return $faces;
    }

    private static function validName(mixed $name): bool
    {
        return is_string($name) && preg_match('/\A[\p{L}\p{N}_-][\p{L}\p{N} _-]*\z/u', trim($name)) === 1;
    }

    private static function stack(mixed $value, string $fallback): string
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback;
        }
        $names = [];
        $hasGeneric = false;
        foreach (explode(',', $value) as $name) {
            $name = trim($name);
            if (strlen($name) >= 2 && in_array($name[0], ['"', "'"], true) && substr($name, -1) === $name[0]) {
                $name = substr($name, 1, -1);
            }
            if (!self::validName($name)) {
                return $fallback;
            }
            $name = trim($name);
            $generic = in_array(strtolower($name), self::GENERICS, true);
            $names[] = $generic ? strtolower($name) : '"'.$name.'"';
            $hasGeneric = $hasGeneric || $generic;
        }
        if (!$hasGeneric) {
            $names[] = 'sans-serif';
        }

        return implode(', ', $names);
    }

    private static function source(mixed $path): ?array
    {
        // Root-relative URLs intentionally bypass asset()/ASSET_URL: fonts stay
        // on this origin even when the rest of the assets use a CDN.
        if (!is_string($path) || !str_starts_with($path, '/fonts/')) {
            return null;
        }
        $segments = explode('/', substr($path, 7));
        foreach ($segments as $segment) {
            if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _.\[\]-]*\z/', $segment) !== 1) {
                return null;
            }
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!isset(self::FORMATS[$extension])) {
            return null;
        }

        return ['src' => '/fonts/'.implode('/', array_map('rawurlencode', $segments)), 'format' => self::FORMATS[$extension]];
    }

    private static function weight(mixed $weight): ?string
    {
        if (!is_string($weight) && !is_int($weight)) {
            return null;
        }
        $weight = (string) $weight;
        if (in_array($weight, ['normal', 'bold'], true)) {
            return $weight;
        }
        if (preg_match('/\A([1-9][0-9]{0,2}|1000)(?: ([1-9][0-9]{0,2}|1000))?\z/', $weight, $matches) !== 1) {
            return null;
        }
        if (isset($matches[2]) && (int) $matches[1] > (int) $matches[2]) {
            return null;
        }

        return $weight;
    }
}
