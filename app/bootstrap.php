<?php
/**
 * Bootstrap: loads config + WordPress, then the app's own libraries.
 * Must be required at the top level of a file (not inside a function),
 * so WordPress globals like $wpdb stay global.
 */

const USP_VERSION    = '0.2.0';
const USP_DB_VERSION = 2;

// Default config values live in lib/settings.php; set or override them in config.php.
require_once __DIR__ . '/lib/settings.php';

switch($usp_config['db_auth_module']){
    case 'wordpress':
    default: // wordpress is the only module we have right now
        if (!is_file($usp_config['wp_load_path'])) {
            http_response_code(500);
            exit('WordPress not found. Set wp_load_path in config.php (see config.sample.php).');
        }
        define('WP_USE_THEMES', false);
        require_once $usp_config['wp_load_path'];
}

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/streaks.php';
require_once __DIR__ . '/lib/content.php';
require_once __DIR__ . '/lib/declarations.php';
require_once __DIR__ . '/lib/entries.php';

usp_maybe_migrate();
