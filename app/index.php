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
$icons = $base . '/assets/icons.svg?v=' . filemtime(__DIR__ . '/assets/icons.svg');
$boot = [
    'api'   => $base . '/api.php',
    'icons' => $icons,
    'loadError' => usp_ui('app.load_error'),
    'version'   => usp_build()['label'],
    'nonce' => wp_create_nonce('usp_api'),
];
$asset = fn (string $file) => $base . '/assets/' . $file . '?v=' . filemtime(__DIR__ . '/assets/' . $file);
$legal = $usp_config['legal_url'] ?: $base . '/legal.php';
// Temporary, for closed testing: the repository's issues page.
$feedback = $usp_config['source_url'] ? rtrim($usp_config['source_url'], '/') . '/issues' : '';

// The app icon: assets/icon-192.png if present, else icon_url from config,
// else a drawn lotus.
$icon = is_file(__DIR__ . '/assets/icon-192.png') ? $asset('icon-192.png') : (string) $usp_config['icon_url'];

/** A Phosphor icon from assets/icons.svg. */
$svg = fn (string $name) => '<svg class="icon" aria-hidden="true" focusable="false"><use href="'
    . esc_url($icons) . '#i-' . $name . '"></use></svg>';

$user_id = get_current_user_id();
$variant = usp_variant_icons($user_id);
$tabs    = [
    'today'    => [usp_ui('nav.today'), 'sun-horizon'],
    'progress' => [usp_ui('nav.progress'), $variant['progress'] ?? 'chart-donut'],
    'reflect'  => [usp_ui('nav.reflect'), 'notebook'],
    'history'  => [usp_ui('nav.history'), 'calendar-blank'],
];
$app_name = usp_app_name();

header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#a9623f">
    <title><?php echo esc_html($app_name); ?></title>
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
            <a class="brand" href="#today" aria-label="<?php echo esc_attr($app_name); ?>, <?php echo esc_attr(usp_ui('nav.today')); ?>">
                <?php if ($icon) : ?>
                <img class="brand__icon" src="<?php echo esc_url($icon); ?>" alt="" width="30" height="30">
                <?php else : ?>
                <span class="brand__icon brand__icon--drawn" aria-hidden="true"><span></span></span>
                <?php endif; ?>
                <span class="brand__name"><?php echo esc_html($app_name); ?></span>
            </a>
            <a class="masthead__icon" href="#declarations" data-view="declarations" aria-label="<?php echo esc_attr(usp_ui('nav.declarations')); ?>" title="<?php echo esc_attr(usp_ui('nav.declarations')); ?>"><?php echo $svg('compass'); ?></a>
            <details class="account">
                <summary class="account__toggle" aria-label="<?php echo esc_attr(usp_ui('nav.settings')); ?>"><?php echo $svg('gear-six'); ?></summary>
                <div class="account__menu">
                    <p class="account__name"><?php echo esc_html(usp_ui('nav.signed_in_as')); ?> <strong><?php echo esc_html(wp_get_current_user()->display_name); ?></strong></p>
                    <a href="#settings"><?php echo $svg('gear-six'); ?> <?php echo esc_html(usp_ui('nav.settings')); ?></a>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo $svg('sign-out'); ?> <?php echo esc_html(usp_ui('nav.log_out')); ?></a>
                </div>
            </details>
        </div>
    </header>

    <main class="app" id="app" aria-live="polite">
        <p class="loading"><?php echo esc_html(usp_ui('app.loading')); ?></p>
    </main>

    <footer class="footer">
        <a href="<?php echo esc_url($legal); ?>"><?php echo esc_html(usp_ui('nav.terms')); ?></a>
        <?php if ($feedback) : ?>
            <span aria-hidden="true">·</span>
            <a href="<?php echo esc_url($feedback); ?>" target="_blank" rel="noopener"><?php echo esc_html(usp_ui('nav.feedback')); ?></a>
        <?php endif; ?>
        <span class="footer__version" title="<?php echo esc_attr(usp_build()['date']); ?>"><?php echo esc_html(usp_build()['label']); ?></span>
    </footer>

    <nav class="tabbar" aria-label="Views">
        <?php foreach ($tabs as $key => [$label, $icon_name]) : ?>
        <a class="tabbar__tab" href="#<?php echo $key; ?>" data-view="<?php echo $key; ?>"><?php echo $svg($icon_name); ?><span><?php echo esc_html($label); ?></span></a>
        <?php endforeach; ?>
    </nav>

    <script>window.USP = <?php echo wp_json_encode($boot); ?>;</script>
    <script src="<?php echo esc_url($asset('app.js')); ?>" defer></script>
</body>
</html>
