<?php
/**
 * A small Markdown renderer for the app's own pages (like LEGAL.md).
 * No dependencies. Supports what those pages use:
 *   # headings (h1-h3), paragraphs, "- " lists, --- rules,
 *   **bold**, *italic*, `code`, [links](https://…), and <!-- hidden notes -->.
 * All text is HTML-escaped, so raw HTML in the Markdown is shown as text.
 */

function usp_markdown(string $md): string
{
    $md    = preg_replace('/<!--.*?-->/s', '', str_replace("\r\n", "\n", $md));
    $lines = explode("\n", $md);
    $html  = [];
    $para  = [];
    $list  = [];

    $flush_para = function () use (&$para, &$html) {
        if ($para) {
            $html[] = '<p>' . usp_markdown_inline(implode(' ', $para)) . '</p>';
            $para   = [];
        }
    };
    $flush_list = function () use (&$list, &$html) {
        if ($list) {
            $items  = array_map(fn ($item) => '<li>' . usp_markdown_inline($item) . '</li>', $list);
            $html[] = "<ul>\n" . implode("\n", $items) . "\n</ul>";
            $list   = [];
        }
    };

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $flush_para();
            $flush_list();
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $m)) {
            $flush_para();
            $flush_list();
            $level  = strlen($m[1]);
            $id     = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(strip_tags($m[2]))), '-');
            $html[] = sprintf('<h%d id="%s">%s</h%1$d>', $level, $id, usp_markdown_inline($m[2]));
            continue;
        }

        if (preg_match('/^(-{3,}|\*{3,})$/', $trimmed)) {
            $flush_para();
            $flush_list();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m)) {
            $flush_para();
            $list[] = $m[1];
            continue;
        }

        if ($list && preg_match('/^\s+/', $line)) {
            // Indented continuation of the previous list item.
            $list[count($list) - 1] .= ' ' . $trimmed;
            continue;
        }

        $flush_list();
        $para[] = $trimmed;
    }

    $flush_para();
    $flush_list();

    return implode("\n", $html);
}

function usp_markdown_inline(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // `code` first, so its contents aren't formatted.
    $codes = [];
    $text  = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x00" . (count($codes) - 1) . "\x00";
    }, $text);

    // [text](url): only http(s), mailto, and relative links.
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^(https?://|mailto:|/|\./|\#)#i', $url)) {
            return $m[0];
        }
        $external = preg_match('#^https?://#i', $url) ? ' rel="noopener"' : '';
        return sprintf('<a href="%s"%s>%s</a>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $external, $m[1]);
    }, $text);

    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $text);

    return preg_replace_callback("/\x00(\d+)\x00/", fn ($m) => $codes[(int) $m[1]], $text);
}
