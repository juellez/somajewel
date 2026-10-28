<?php
/**
 * Public welcome page to share with friends and clients: what the app is,
 * and how to join. No login needed.
 *
 * "Join" goes to WordPress registration, so it works only while WordPress
 * allows sign-ups (Settings → General → Membership: "Anyone can register",
 * with new users as Subscriber). When sign-ups are off, the page says the
 * beta is invitation-only instead. Wording: content/prompts.php → ui → welcome.
 */

require __DIR__ . '/bootstrap.php';

$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$app_url  = set_url_scheme('//' . $_SERVER['HTTP_HOST'] . $base . '/');
$asset    = fn (string $file) => $base . '/assets/' . $file . '?v=' . filemtime(__DIR__ . '/assets/' . $file);
$icons    = $asset('icons.svg');
$svg      = fn (string $name) => '<svg class="icon" aria-hidden="true" focusable="false"><use href="' . esc_url($icons) . '#i-' . $name . '"></use></svg>';
$w        = usp_content()['ui']['welcome'] ?? [];
$t        = fn (string $key) => esc_html((string) ($w[$key] ?? ''));
$app_name = usp_app_name();
$icon     = is_file(__DIR__ . '/assets/icon-192.png') ? $asset('icon-192.png') : (string) $usp_config['icon_url'];
$legal    = $usp_config['legal_url'] ?: $base . '/legal.php';

$signed_in = is_user_logged_in();
$can_join  = (bool) get_option('users_can_register');

header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#a9623f">
    <title><?php echo esc_html($app_name); ?> · <?php echo $t('tagline'); ?></title>
    <meta name="description" content="<?php echo esc_attr($w['sub'] ?? ''); ?>">
    <!-- How the link looks when shared in Messages, email, or social -->
    <meta property="og:title" content="<?php echo esc_attr($app_name); ?>">
    <meta property="og:description" content="<?php echo esc_attr($w['tagline'] ?? ''); ?>">
    <meta property="og:url" content="<?php echo esc_url(set_url_scheme('//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])); ?>">
    <?php if ($icon) : ?>
    <meta property="og:image" content="<?php echo esc_url(set_url_scheme('//' . $_SERVER['HTTP_HOST'] . strtok($icon, '?'))); ?>">
    <link rel="icon" href="<?php echo esc_url($icon); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo esc_url($asset('app.css')); ?>">
</head>
<body class="welcome-page">
    <header class="welcome__hero">
        <?php if ($icon) : ?>
        <img class="welcome__icon" src="<?php echo esc_url($icon); ?>" alt="" width="64" height="64">
        <?php else : ?>
        <span class="welcome__icon brand__icon--drawn" aria-hidden="true"><span></span></span>
        <?php endif; ?>
        <h1 class="welcome__name"><?php echo esc_html($app_name); ?></h1>
        <p class="welcome__tagline"><?php echo $t('tagline'); ?></p>
        <p class="welcome__sub"><?php echo $t('sub'); ?></p>
    </header>

    <main class="app welcome">
        <?php if (!empty($w['badge'])) : ?><span class="welcome__badge"><?php echo $t('badge'); ?></span><?php endif; ?>
        <?php if (!empty($w['why'])) : ?><p class="welcome__why"><?php echo $t('why'); ?></p><?php endif; ?>

        <section>
            <h2 class="welcome__heading"><?php echo $t('what_title'); ?></h2>
            <ul class="welcome__list">
                <?php foreach ((array) ($w['what'] ?? []) as $item) : ?>
                <li>
                    <?php echo $svg((string) ($item['icon'] ?? 'circle')); ?>
                    <div><strong><?php echo esc_html($item['title'] ?? ''); ?></strong><span><?php echo esc_html($item['text'] ?? ''); ?></span></div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if (!empty($w['quote'])) : ?><blockquote class="welcome__quote"><?php echo $t('quote'); ?></blockquote><?php endif; ?>

        <section class="card welcome__join">
            <?php if ($signed_in) : ?>
                <a class="btn btn--primary" href="<?php echo esc_url($app_url); ?>"><?php echo $t('open'); ?></a>
            <?php else : ?>
                <h2 class="welcome__heading"><?php echo $t('join_title'); ?></h2>
                <?php if ($can_join) : ?>
                    <p><?php echo $t('join_body'); ?></p>
                    <a class="btn btn--primary" href="<?php echo esc_url(wp_registration_url()); ?>"><?php echo $svg('user-plus'); ?> <?php echo $t('join'); ?></a>
                <?php else : ?>
                    <p><?php echo $t('closed'); ?></p>
                <?php endif; ?>
                <a class="btn" href="<?php echo esc_url(wp_login_url($app_url)); ?>"><?php echo $svg('sign-in'); ?> <?php echo $t('login'); ?></a>
            <?php endif; ?>
        </section>

        <p class="welcome__private"><?php echo $t('private'); ?></p>
    </main>

    <footer class="footer">
        <a href="<?php echo esc_url($legal); ?>"><?php echo esc_html(usp_ui('nav.terms')); ?></a>
        <span class="footer__version"><?php echo esc_html(usp_build()['label']); ?></span>
    </footer>
</body>
</html>
