<?php
/**
 * After login, send everyone except admins to the app.
 *
 * Only replaces WordPress's default destination (the dashboard). If someone
 * was headed somewhere specific, such as the app itself or a members page,
 * they still go there.
 *
 * The app's address defaults to /app/ on this site's domain. To change it,
 * add this line to wp-config.php:
 *   define('SOMAJEWEL_APP_URL', 'https://example.com/app/');
 */

defined('ABSPATH') || exit;

function sjs_app_url(): string
{
    if (defined('SOMAJEWEL_APP_URL')) {
        return SOMAJEWEL_APP_URL;
    }
    // The app sits at the domain root, even when WordPress is in a subfolder.
    $home = wp_parse_url(home_url());
    return ($home['scheme'] ?? 'https') . '://' . $home['host']
        . (isset($home['port']) ? ':' . $home['port'] : '') . '/app/';
}

add_filter('login_redirect', function ($redirect_to, $requested, $user) {
    if (!$user instanceof WP_User || user_can($user, 'manage_options')) {
        return $redirect_to;
    }
    // Nothing requested, or just the dashboard: that's WordPress's default.
    $path = (string) wp_parse_url($requested, PHP_URL_PATH);
    if ($requested === '' || str_contains($path, '/wp-admin')) {
        return sjs_app_url();
    }
    return $redirect_to;
}, 20, 3);

// Let wp_safe_redirect() send people to the app if it's on another host.
add_filter('allowed_redirect_hosts', function (array $hosts) {
    $host = wp_parse_url(sjs_app_url(), PHP_URL_HOST);
    if ($host && !in_array($host, $hosts, true)) {
        $hosts[] = $host;
    }
    return $hosts;
});
