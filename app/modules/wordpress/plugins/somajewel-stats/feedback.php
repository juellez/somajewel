<?php
/**
 * Practice stats → Feedback: what testers sent from the app's
 * "Share Feedback" screen, and the tally of the name poll.
 * This is the one place notes are shown, because testers wrote them to you.
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('somajewel-stats', 'Feedback', 'Feedback', 'manage_options', 'somajewel-feedback', 'sjs_render_feedback');
}, 11); // after the main Practice stats menu exists

/** "iPhone · Safari" from a user agent. */
function sjs_device(?string $ua): string
{
    if (!$ua) {
        return '—';
    }
    $os = match (true) {
        str_contains($ua, 'iPhone')    => 'iPhone',
        str_contains($ua, 'iPad')      => 'iPad',
        str_contains($ua, 'Android')   => 'Android',
        str_contains($ua, 'Macintosh') => 'Mac',
        str_contains($ua, 'Windows')   => 'Windows',
        str_contains($ua, 'Linux')     => 'Linux',
        default                        => 'Other',
    };
    $browser = match (true) {
        str_contains($ua, 'Edg/')     => 'Edge',
        str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
        str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/')  => 'Safari',
        default                       => 'Browser',
    };
    return "$os · $browser";
}

function sjs_render_feedback(): void
{
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    $table = sjs_table('feedback');
    echo '<div class="wrap"><h1>Feedback</h1>';

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        echo '<div class="notice notice-warning"><p>No feedback table yet. The app creates it the next time someone opens it.</p></div></div>';
        return;
    }

    // Name poll: each person's latest vote counts once.
    $votes = $wpdb->get_results("
        SELECT f.name_vote, f.name_other
        FROM $table f
        JOIN (SELECT user_id, MAX(id) AS id FROM $table WHERE name_vote IS NOT NULL GROUP BY user_id) latest ON latest.id = f.id
    ", ARRAY_A) ?: [];

    $tally = [];
    $write_ins = [];
    foreach ($votes as $v) {
        if ($v['name_vote'] === 'other') {
            $write_ins[] = $v['name_other'];
            $tally['Something else'] = ($tally['Something else'] ?? 0) + 1;
        } else {
            $tally[$v['name_vote']] = ($tally[$v['name_vote']] ?? 0) + 1;
        }
    }
    arsort($tally);

    echo '<h2>Name poll</h2>';
    if (!$tally) {
        echo '<p class="sjs-muted">No votes yet.</p>';
    } else {
        printf('<p class="sjs-muted">%d %s. Each person\'s latest vote counts.</p>', count($votes), count($votes) === 1 ? 'person has voted' : 'people have voted');
        echo '<div class="sjs-cards">';
        foreach ($tally as $name => $n) {
            printf('<div class="sjs-card"><b>%d</b><span>%s</span></div>', $n, esc_html($name));
        }
        echo '</div>';
        if ($write_ins) {
            echo '<p><strong>Write-ins:</strong> ' . esc_html(implode(' · ', $write_ins)) . '</p>';
        }
    }

    // Practices people added for themselves: names only, to see what's missing
    // from the built-in list. (How they practice them stays private.)
    $practices = sjs_table('user_practices');
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $practices)) === $practices) {
        $added = $wpdb->get_results("
            SELECT LOWER(TRIM(title)) AS name, MIN(title) AS title, COUNT(DISTINCT user_id) AS people
            FROM $practices WHERE deleted = 0
            GROUP BY LOWER(TRIM(title)) ORDER BY people DESC, title
        ", ARRAY_A) ?: [];
        echo '<h2 style="margin-top:28px">Practices people added</h2>';
        if (!$added) {
            echo '<p class="sjs-muted">None yet.</p>';
        } else {
            echo '<p>' . implode(' · ', array_map(fn ($r) => esc_html($r['title']) . ((int) $r['people'] > 1 ? ' <span class="sjs-muted">(' . (int) $r['people'] . ')</span>' : ''), $added)) . '</p>';
        }
    }

    $rows = $wpdb->get_results("
        SELECT f.*, u.display_name
        FROM $table f
        LEFT JOIN {$wpdb->users} u ON u.ID = f.user_id
        ORDER BY f.id DESC
        LIMIT 200
    ", ARRAY_A) ?: [];

    $kinds = ['issue' => 'Something\'s off', 'idea' => 'Idea', 'naming' => 'Naming'];

    echo '<h2 style="margin-top:28px">Notes</h2>';
    if (!$rows) {
        echo '<p>No feedback yet.</p></div>';
        return;
    }

    echo '<table class="widefat striped sjs-table"><thead><tr>
        <th>When</th><th>Who</th><th>Kind</th><th style="width:40%">Note</th><th>Vote</th><th>Screen</th><th>Device</th><th>Version</th>
    </tr></thead><tbody>';
    foreach ($rows as $r) {
        $vote = $r['name_vote'] === 'other' ? '“' . $r['name_other'] . '”' : (string) $r['name_vote'];
        printf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td></tr>',
            esc_html(mysql2date('M j, g:ia', $r['created_at'])),
            esc_html($r['display_name'] ?: '(deleted user)'),
            esc_html($kinds[$r['kind']] ?? $r['kind']),
            nl2br(esc_html((string) $r['message'])),
            esc_html($vote ?: '—'),
            esc_html($r['screen'] ?: '—'),
            esc_html(sjs_device($r['device'])),
            esc_html((string) $r['version'])
        );
    }
    echo '</tbody></table></div>';
}
