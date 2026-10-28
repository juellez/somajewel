<?php
/**
 * SomaJewel → My Activity: the signed-in admin's own rows, straight from the
 * database, for checking the app against what's stored. Only ever your own
 * data; other people's notes and answers stay private.
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_submenu_page('somajewel-stats', 'My Activity', 'My Activity', 'manage_options', 'somajewel-my-activity', 'sjs_render_my_activity');
}, 11); // after the main menu exists

const SJS_ACTIVITY_RANGES = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 0 => 'Everything'];

function sjs_render_my_activity(): void
{
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    $user_id = get_current_user_id();
    echo '<div class="wrap"><h1>My Activity</h1>';
    echo '<p class="sjs-muted">Your own entries as stored, newest first. Keys are the stored values (skill, mood), not their display names. Only you see this, and only for your own account.</p>';

    if (!sjs_tables_exist()) {
        echo '<div class="notice notice-warning"><p>No app tables yet.</p></div></div>';
        return;
    }

    $days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
    if (!array_key_exists($days, SJS_ACTIVITY_RANGES)) {
        $days = 7;
    }

    // Profile: time zone, streaks, week start.
    $profile = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . sjs_table('profiles') . ' WHERE user_id = %d', $user_id), ARRAY_A);
    if ($profile) {
        echo '<div class="sjs-cards">';
        foreach ([
            'Time zone'      => $profile['timezone'] . (empty($profile['timezone_set']) ? ' (default)' : ''),
            'Week starts'    => isset($profile['week_start']) ? ((int) $profile['week_start'] === 0 ? 'Sunday' : 'Monday') : '—',
            'Current streak' => $profile['current_streak'],
            'Longest'        => $profile['longest_streak'],
            'Last check-in'  => $profile['last_checkin_date'] ?: '—',
        ] as $label => $value) {
            printf('<div class="sjs-card"><b>%s</b><span>%s</span></div>', esc_html((string) $value), esc_html($label));
        }
        echo '</div>';
    } else {
        echo '<p>You haven\'t opened the app with this account yet.</p>';
    }

    // Range picker.
    echo '<p>';
    $links = [];
    foreach (SJS_ACTIVITY_RANGES as $n => $label) {
        $url     = add_query_arg(['page' => 'somajewel-my-activity', 'days' => $n], admin_url('admin.php'));
        $links[] = $n === $days ? '<strong>' . esc_html($label) . '</strong>' : '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
    echo implode(' · ', $links) . '</p>';

    // Declarations, by id (entries refer to them by id).
    $decls = $wpdb->get_results($wpdb->prepare(
        'SELECT id, status, text, created_at FROM ' . sjs_table('declarations') . ' WHERE user_id = %d ORDER BY id',
        $user_id
    ), ARRAY_A) ?: [];
    echo '<h2>Declarations</h2>';
    if ($decls) {
        echo '<table class="widefat striped sjs-table"><thead><tr><th>ID</th><th>Status</th><th>Text</th><th>Created</th></tr></thead><tbody>';
        foreach ($decls as $d) {
            printf('<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td></tr>', $d['id'], esc_html($d['status']), esc_html($d['text']), esc_html($d['created_at']));
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="sjs-muted">None yet.</p>';
    }

    // Entries.
    $where = 'user_id = %d';
    $args  = [$user_id];
    if ($days) {
        $where .= ' AND entry_date >= %s';
        $args[] = gmdate('Y-m-d', strtotime("-$days days"));
    }
    $entries = $wpdb->get_results($wpdb->prepare(
        'SELECT id, entry_date, type, skill_key, declaration_id, mood, answers, note, created_at
         FROM ' . sjs_table('entries') . " WHERE $where ORDER BY entry_date DESC, id DESC",
        ...$args
    ), ARRAY_A) ?: [];

    printf('<h2>Entries <span class="sjs-badge">%d</span></h2>', count($entries));
    if ($entries) {
        echo '<p class="sjs-muted">"Logged" is when it was saved, in the database server\'s clock (often UTC). A date earlier than its logged day means it was logged for yesterday, during the grace window.</p>';
        echo '<table class="widefat striped sjs-table"><thead><tr><th>ID</th><th>Day</th><th>Type</th><th>Skill</th><th>Decl.</th><th>Mood</th><th>Answers / note</th><th>Logged</th></tr></thead><tbody>';
        foreach ($entries as $e) {
            $text = [];
            if ($e['answers']) {
                foreach (json_decode($e['answers'], true) ?: [] as $k => $v) {
                    $text[] = '<b>' . esc_html($k) . ':</b> ' . esc_html(is_scalar($v) ? (string) $v : wp_json_encode($v));
                }
            }
            if ($e['note'] !== null && $e['note'] !== '') {
                $text[] = '<i>' . esc_html($e['note']) . '</i>';
            }
            printf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $e['id'],
                esc_html($e['entry_date']),
                esc_html($e['type']),
                esc_html($e['skill_key'] ?? '—'),
                $e['declaration_id'] ? (int) $e['declaration_id'] : '—',
                esc_html($e['mood'] ?? '—'),
                implode('<br>', $text) ?: '<span class="sjs-muted">—</span>',
                esc_html($e['created_at'])
            );
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="sjs-muted">Nothing logged in this range.</p>';
    }

    // Weekly reflections.
    $weekly = $wpdb->get_results($wpdb->prepare(
        'SELECT week_key, prompt_key, response, updated_at FROM ' . sjs_table('weekly_responses') . '
         WHERE user_id = %d ORDER BY week_key DESC, id LIMIT 60',
        $user_id
    ), ARRAY_A) ?: [];
    echo '<h2>Weekly reflections</h2>';
    if ($weekly) {
        echo '<table class="widefat striped sjs-table"><thead><tr><th>Week (Thursday key)</th><th>Question key</th><th>Answer</th><th>Updated</th></tr></thead><tbody>';
        foreach ($weekly as $w) {
            printf('<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>', esc_html($w['week_key']), esc_html($w['prompt_key']), nl2br(esc_html((string) $w['response'])), esc_html($w['updated_at']));
        }
        echo '</tbody></table>';
    } else {
        echo '<p class="sjs-muted">None yet.</p>';
    }

    echo '</div>';
}
