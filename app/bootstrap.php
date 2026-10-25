<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Jewel Mlnarik · https://github.com/juellez/somajewel

/**
 * Bootstrap: loads config + WordPress, then the app's own libraries.
 * Must be required at the top level of a file (not inside a function),
 * so WordPress globals like $wpdb stay global.
 */

const USP_VERSION    = '0.2.0';
const USP_DB_VERSION = 2;

$usp_config = [
    // Default assumes the app folder sits inside the WordPress root (e.g. /app).
    'wp_load_path'     => dirname(__DIR__) . '/wp-load.php',
    'default_timezone' => 'America/Chicago',
    // Shown in the footer. If you run a modified version, point this at your
    // modified source code (AGPL section 13).
    'source_url'       => 'https://github.com/juellez/somajewel',
];

if (is_file(__DIR__ . '/config.php')) {
    $usp_config = array_merge($usp_config, (array) require __DIR__ . '/config.php');
}

if (!is_file($usp_config['wp_load_path'])) {
    http_response_code(500);
    exit('WordPress not found. Set wp_load_path in config.php (see config.sample.php).');
}

define('WP_USE_THEMES', false);
require_once $usp_config['wp_load_path'];

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/streaks.php';
require_once __DIR__ . '/lib/content.php';
require_once __DIR__ . '/lib/declarations.php';
require_once __DIR__ . '/lib/entries.php';

usp_maybe_migrate();
