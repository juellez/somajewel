<?php
/**
 * Terms & Privacy page, rendered from LEGAL.md.
 * Public (no login) and independent of WordPress, so it's always readable.
 *
 * In LEGAL.md, these placeholders are filled in from config:
 *   [CONTACT_EMAIL]  contact_email, as a mailto link
 *   [SOURCE_URL]     source_url, as a link
 * Wrap notes you don't want shown in <!-- HTML comments -->.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/markdown.php';

$markdown = (string) @file_get_contents(__DIR__ . '/LEGAL.md');
$email    = trim((string) $usp_config['contact_email']);
$source   = trim((string) $usp_config['source_url']);

$markdown = strtr($markdown, [
    '[CONTACT_EMAIL]' => $email !== '' ? "[$email](mailto:$email)" : '*(contact email not set)*',
    '[SOURCE_URL]'    => $source !== '' ? "[$source]($source)" : '*(source link not set)*',
]);

$title = preg_match('/^#\s+(.+)$/m', $markdown, $m) ? trim($m[1]) : 'Terms & Privacy';
$css   = 'assets/app.css?v=' . filemtime(__DIR__ . '/assets/app.css');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> · SomaJewel</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <main class="app legal">
        <p class="legal__back"><a href="./">← Back to your practice</a></p>
        <article class="card legal__body">
            <?php echo $markdown !== '' ? usp_markdown($markdown) : '<p>This page is being written.</p>'; ?>
        </article>
    </main>
</body>
</html>
