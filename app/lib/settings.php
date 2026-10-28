<?php
/**
 * App configuration: defaults below, overridden by config.php (not in git).
 * Loaded without WordPress, so standalone pages like legal.php can use it.
 * Require this at the top level of a file so $usp_config stays global.
 */

$usp_config = [
    'db_auth_module'   => 'wordpress', // wordpress module for authentication and DB connection
    'wp_load_path'     => dirname(__DIR__, 2) . '/wp-load.php', // app folder is nested in WP root
    'default_timezone' => 'America/Chicago',
    'legal_url'        => '', // footer link; empty = the app's own legal.php page
    'source_url'       => 'https://github.com/juellez/somajewel', // shared on legal page
    'contact_email'    => '', // shared on legal page
    'icon_url'         => '', // app icon if assets/icon-192.png isn't present
    'show_invite'      => true, // "Invite a Friend" in Settings and the footer
];

if (is_file(dirname(__DIR__) . '/config.php')) {
    $usp_config = array_merge($usp_config, (array) require dirname(__DIR__) . '/config.php');
}
