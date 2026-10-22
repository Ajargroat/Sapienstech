<?php

/**
 * Server-only overrides for the corporate site (sapienstech.ir).
 *
 * Copy this file to `corporate-config.php` in the same folder — the repository
 * root — and fill in what you need. That path is gitignored and sits outside
 * the corporate site's web root, so it is never served or committed.
 *
 * Anything you set here wins over the Laravel app's ../.env, which is the
 * fallback. In the normal case you can leave every key commented out: the
 * corporate site then talks to the same database as the app.
 *
 * If you ever expose the lead form to another origin, set ALLOWED_ORIGIN to
 * that single origin (not "*").
 */

return [
    // 'DB_HOST'         => '127.0.0.1',
    // 'DB_PORT'         => 3306,
    // 'DB_NAME'         => 'sapienstech',
    // 'DB_USER'         => 'sapien',
    // 'DB_PASS'         => '…',
    // 'ALLOWED_ORIGIN'  => 'https://sapienstech.ir',
];
