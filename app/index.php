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
$legal = $usp_config['legal_url'] ?: $base . '/legal.php';

// The app icon: assets/icon-192.png if present, else icon_url from config,
// else a drawn lotus.
$icon = is_file(__DIR__ . '/assets/icon-192.png') ? $asset('icon-192.png') : (string) $usp_config['icon_url'];

header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#a9623f">
    <title>SomaJewel</title>
    <?php if ($icon) : ?>
    <link rel="icon" href="<?php echo esc_url($icon); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon); ?>">
    <?php endif; ?>
    <link rel="preload" href="<?php echo esc_url($asset('fonts/jost-300.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo esc_url($asset('app.css')); ?>">
</head>
<body>
    <header class="masthead">
        <div class="masthead__inner">
            <a class="brand" href="#today" aria-label="SomaJewel, today">
                <?php if ($icon) : ?>
                <img class="brand__icon" src="<?php echo esc_url($icon); ?>" alt="" width="30" height="30">
                <?php else : ?>
                <span class="brand__icon brand__icon--drawn" aria-hidden="true"><span></span></span>
                <?php endif; ?>
                <span class="brand__name">SomaJewel</span>
            </a>
            <nav class="tabs" aria-label="Views">
                <a class="tabs__tab" href="#today" data-view="today">Today</a>
                <a class="tabs__tab" href="#history" data-view="history">History</a>
            </nav>
        </div>
    </header>

    <main class="app" id="app" aria-live="polite">
        <p class="loading">Loading your practice…</p>
    </main>

    <footer class="footer">
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
        <span aria-hidden="true">·</span>
        <a href="<?php echo esc_url($legal); ?>">Terms of Use &amp; Privacy</a>
    </footer>

    <script>window.USP = <?php echo wp_json_encode($boot); ?>;</script>
    <script src="<?php echo esc_url($asset('app.js')); ?>" defer></script>
</body>
</html>
