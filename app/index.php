<?php
/**
 * App shell. Sends logged-out visitors to the WordPress login,
 * then hands off to assets/app.js, which talks to api.php.
 */

require __DIR__ . '/bootstrap.php';

if (!is_user_logged_in()) {
    $return = set_url_scheme('//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    wp_safe_redirect(wp_login_url($return));
    exit;
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$boot = [
    'api'   => $base . '/api.php',
    'nonce' => wp_create_nonce('usp_api'),
];
$asset = fn (string $file) => $base . '/assets/' . $file . '?v=' . filemtime(__DIR__ . '/assets/' . $file);

header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Practice · Unstruck Somatics</title>
    <link rel="stylesheet" href="<?php echo esc_url($asset('app.css')); ?>">
</head>
<body>
    <main class="app" id="app" aria-live="polite">
        <p class="loading">Loading your practice…</p>
    </main>

    <footer class="footer">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
        <span aria-hidden="true">·</span>
        <a href="<?php echo esc_url($usp_config['legal_url']); ?>">Terms of Use & Privacy</a>
    </footer>

    <script>window.USP = <?php echo wp_json_encode($boot); ?>;</script>
    <script src="<?php echo esc_url($asset('app.js')); ?>" defer></script>
</body>
</html>
