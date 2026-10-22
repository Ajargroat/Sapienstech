<?php

/**
 * Corporate site (sapienstech.ir) — database and CORS configuration.
 *
 * No credentials live in this file. They are resolved, in order, from:
 *
 *   1. ../corporate-config.php  — server-only overrides, gitignored
 *   2. ../.env                  — the Laravel app's own settings
 *
 * Both sit OUTSIDE this site's web root (corporate-site/), so neither is
 * reachable over HTTP. See corporate-config.example.php and
 * docs/corporate-site.md.
 *
 * This script is loaded by api.php, which does not boot Laravel, so the .env
 * values have to be read here rather than through config().
 */

$root = dirname(__DIR__);

/** Server-only overrides. Never committed; see .gitignore. */
$overrides = [];
if (is_file($root . '/corporate-config.php')) {
    $loaded = require $root . '/corporate-config.php';
    if (is_array($loaded)) {
        $overrides = $loaded;
    }
}

/** Minimal .env reader — dotenv is not available to a standalone script. */
$env = [];
if (is_readable($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        // Tolerate a UTF-8 BOM written by a Windows editor.
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        $quoted = false;
        if (strlen($value) > 1
            && ($value[0] === '"' || $value[0] === "'")
            && str_ends_with($value, $value[0])) {
            $value  = substr($value, 1, -1);
            $quoted = true;
        }

        // Unquoted values: whitespace followed by # starts a comment, matching
        // Laravel's dotenv parser.
        if (! $quoted && preg_match('/\s+#/', $value, $match, PREG_OFFSET_CAPTURE)) {
            $value = rtrim(substr($value, 0, $match[0][1]));
        }

        $env[$key] = $value;
    }
}

/** First non-empty match wins: overrides, then .env, then the default. */
$setting = static function (array $keys, ?string $default = null) use ($overrides, $env): ?string {
    foreach ($keys as $key) {
        if (isset($overrides[$key]) && $overrides[$key] !== '') {
            return (string) $overrides[$key];
        }
    }

    foreach ($keys as $key) {
        if (isset($env[$key]) && $env[$key] !== '') {
            return $env[$key];
        }
    }

    return $default;
};

// ─── Database ─────────────────────────────────────────────────────────────
// Keys are accepted in both this site's spelling and Laravel's (.env) spelling.
define('DB_HOST', $setting(['DB_HOST'], '127.0.0.1'));
define('DB_PORT', (int) $setting(['DB_PORT'], '3306'));
define('DB_NAME', $setting(['DB_NAME', 'DB_DATABASE'], ''));
define('DB_USER', $setting(['DB_USER', 'DB_USERNAME'], ''));
define('DB_PASS', $setting(['DB_PASS', 'DB_PASSWORD'], ''));
define('DB_CHARSET', 'utf8mb4');

// ─── CORS ─────────────────────────────────────────────────────────────────
// The page and api.php share an origin, so this is belt-and-braces only.
define('ALLOWED_ORIGIN', $setting(['ALLOWED_ORIGIN'], 'https://sapienstech.ir'));
