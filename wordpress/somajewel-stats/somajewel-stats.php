<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 Jewel Mlnarik · https://github.com/juellez/somajewel

/**
 * Plugin Name: SomaJewel Stats
 * Description: Usage stats for the SomaJewel practice app: streaks, counts, and activity. Never shows journal content, notes, answers, moods, or declaration text.
 * Version:     0.1.0
 * Author:      Unstruck Somatics
 * License:     GPL-2.0-or-later
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

const SJS_DAYS = 14; // width of the activity strip

add_action('admin_menu', function () {
    add_menu_page(
        'Practice stats',
        'Practice stats',
        'manage_options',
        'somajewel-stats',
        'sjs_render_page',
        'dashicons-chart-bar',
        80
    );
});

add_action('admin_head', function () {
    if (($_GET['page'] ?? '') !== 'somajewel-stats') {
        return;
    }
    ?>
    <style>
        .sjs-cards { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0 24px; }
        .sjs-card { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 16px; min-width: 130px; }
        .sjs-card b { display: block; font-size: 24px; line-height: 1.2; }
        .sjs-card span { color: #646970; }
        .sjs-table td, .sjs-table th { vertical-align: middle; }
        .sjs-num { text-align: right; font-variant-numeric: tabular-nums; }
        .sjs-strip { display: flex; gap: 3px; }
        .sjs-day { width: 12px; height: 12px; border-radius: 2px; background: #e6e6e6; }
        .sjs-day.l1 { background: #e9b8a2; }
        .sjs-day.l2 { background: #c97a57; }
        .sjs-day.l3 { background: #a4532f; }
        .sjs-muted { color: #646970; }
        .sjs-badge { display: inline-block; padding: 0 6px; border-radius: 3px; background: #f0f0f1; font-size: 12px; }
    </style>
    <?php
});

function sjs_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . 'usp_' . $name;
}

function sjs_tables_exist(): bool
{
    global $wpdb;
    $table = sjs_table('profiles');
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function sjs_render_page(): void
{
    global $wpdb;

    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    echo '<div class="wrap"><h1>Practice stats</h1>';

    if (!sjs_tables_exist()) {
        printf(
            '<div class="notice notice-warning"><p>No app tables found in this site\'s database (looked for <code>%s</code>). '
            . 'The app creates them the first time someone opens it, so either nobody has used the app yet, '
            . 'or the app is connected to a different WordPress site.</p></div></div>',
            esc_html(sjs_table('profiles'))
        );
        return;
    }

    $profiles     = sjs_table('profiles');
    $entries      = sjs_table('entries');
    $declarations = sjs_table('declarations');
    $weekly       = sjs_table('weekly_responses');

    // Dates for the activity strip, oldest first, in the site's timezone.
    $days = [];
    for ($i = SJS_DAYS - 1; $i >= 0; $i--) {
        $days[] = wp_date('Y-m-d', strtotime("-$i days"));
    }
    $today = end($days);
    $since = $days[0];

    $users = $wpdb->get_results("
        SELECT p.*, u.user_login, u.display_name,
            (SELECT COUNT(*) FROM $entries e WHERE e.user_id = p.user_id) AS total,
            (SELECT COUNT(*) FROM $entries e WHERE e.user_id = p.user_id AND e.type = 'clearing')  AS clearing,
            (SELECT COUNT(*) FROM $entries e WHERE e.user_id = p.user_id AND e.type = 'practice')  AS practice,
            (SELECT COUNT(*) FROM $entries e WHERE e.user_id = p.user_id AND e.type = 'gratitude') AS gratitude,
            (SELECT COUNT(*) FROM $entries e WHERE e.user_id = p.user_id AND e.type = 'rest')      AS rest,
            (SELECT COUNT(*) FROM $declarations d WHERE d.user_id = p.user_id AND d.status = 'active') AS decls,
            (SELECT COUNT(*) FROM $weekly w WHERE w.user_id = p.user_id AND TRIM(w.response) <> '') AS weekly_answers,
            GREATEST(
                COALESCE((SELECT MAX(e.created_at) FROM $entries e WHERE e.user_id = p.user_id), '1970-01-01'),
                COALESCE((SELECT MAX(w.updated_at) FROM $weekly w WHERE w.user_id = p.user_id), '1970-01-01'),
                p.created_at
            ) AS last_active
        FROM $profiles p
        LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
        ORDER BY last_active DESC
    ", ARRAY_A) ?: [];

    // Entries per user per day, for the strip.
    $per_day = [];
    $rows    = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, entry_date, COUNT(*) AS n FROM $entries WHERE entry_date >= %s GROUP BY user_id, entry_date",
        $since
    ), ARRAY_A) ?: [];
    foreach ($rows as $row) {
        $per_day[(int) $row['user_id']][$row['entry_date']] = (int) $row['n'];
    }

    // Summary cards.
    $active_today = 0;
    $active_week  = 0;
    $week_start   = $days[SJS_DAYS - 7];
    foreach ($per_day as $dates) {
        if (isset($dates[$today])) {
            $active_today++;
        }
        foreach ($dates as $date => $n) {
            if ($date >= $week_start) {
                $active_week++;
                break;
            }
        }
    }
    $entries_week = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $entries WHERE entry_date >= %s",
        $week_start
    ));

    $cards = [
        'App users'           => count($users),
        'Active today'        => $active_today,
        'Active last 7 days'  => $active_week,
        'Entries last 7 days' => $entries_week,
    ];

    echo '<p class="sjs-muted">Counts only. Notes, answers, moods, and declaration text are never shown here.</p>';
    echo '<div class="sjs-cards">';
    foreach ($cards as $label => $value) {
        printf('<div class="sjs-card"><b>%d</b><span>%s</span></div>', $value, esc_html($label));
    }
    echo '</div>';

    // Users table.
    echo '<h2>Users</h2>';
    if (!$users) {
        echo '<p>Nobody has opened the app yet.</p>';
    } else {
        echo '<table class="widefat striped sjs-table"><thead><tr>
            <th>User</th>
            <th>Last ' . SJS_DAYS . ' days</th>
            <th class="sjs-num">Streak</th>
            <th class="sjs-num">Best</th>
            <th class="sjs-num">Clearing</th>
            <th class="sjs-num">Practice</th>
            <th class="sjs-num">Gratitude</th>
            <th class="sjs-num">Rest</th>
            <th class="sjs-num">Declarations</th>
            <th class="sjs-num">Weekly answers</th>
            <th>Last active</th>
            <th>Joined app</th>
        </tr></thead><tbody>';

        foreach ($users as $u) {
            $uid   = (int) $u['user_id'];
            $strip = '';
            foreach ($days as $d) {
                $n      = $per_day[$uid][$d] ?? 0;
                $level  = $n === 0 ? '' : ($n === 1 ? 'l1' : ($n <= 3 ? 'l2' : 'l3'));
                $strip .= sprintf(
                    '<span class="sjs-day %s" title="%s: %d %s"></span>',
                    $level,
                    esc_attr($d),
                    $n,
                    $n === 1 ? 'entry' : 'entries'
                );
            }

            $name = $u['display_name'] ?: '(deleted user)';
            printf(
                '<tr>
                    <td><strong>%s</strong><br><span class="sjs-muted">%s · ID %d · %s</span></td>
                    <td><div class="sjs-strip">%s</div></td>
                    <td class="sjs-num">%d%s</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td class="sjs-num">%d</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                esc_html($name),
                esc_html($u['user_login'] ?? ''),
                $uid,
                esc_html($u['timezone']),
                $strip,
                (int) $u['current_streak'],
                $u['streak_freezes'] ? ' <span class="sjs-badge" title="Streak freezes">❄ ' . (int) $u['streak_freezes'] . '</span>' : '',
                (int) $u['longest_streak'],
                (int) $u['clearing'],
                (int) $u['practice'],
                (int) $u['gratitude'],
                (int) $u['rest'],
                (int) $u['decls'],
                (int) $u['weekly_answers'],
                esc_html(sjs_ago($u['last_active'])),
                esc_html(mysql2date('M j, Y', $u['created_at']))
            );
        }
        echo '</tbody></table>';
        echo '<p class="sjs-muted">The streak shows the stored value; a broken streak resets the next time that person logs something.</p>';
    }

    // Recent activity: type and skill only.
    $recent = $wpdb->get_results("
        SELECT e.created_at, e.entry_date, e.type, e.skill_key, e.declaration_id,
               (e.note IS NOT NULL) AS has_note, (e.answers IS NOT NULL) AS has_answers,
               u.display_name, e.user_id
        FROM $entries e
        LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
        ORDER BY e.id DESC
        LIMIT 30
    ", ARRAY_A) ?: [];

    echo '<h2 style="margin-top:32px">Recent activity</h2>';
    if (!$recent) {
        echo '<p>No entries yet.</p>';
    } else {
        echo '<table class="widefat striped sjs-table"><thead><tr>
            <th>When (server time)</th><th>Their date</th><th>User</th><th>Type</th><th>Skill</th><th>Declaration</th><th>Wrote something</th>
        </tr></thead><tbody>';
        foreach ($recent as $r) {
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s <span class="sjs-muted">(ID %d)</span></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($r['created_at']),
                esc_html($r['entry_date']),
                esc_html($r['display_name'] ?: '(deleted user)'),
                (int) $r['user_id'],
                esc_html($r['type']),
                esc_html($r['skill_key'] ?: '—'),
                $r['declaration_id'] ? '#' . (int) $r['declaration_id'] : '—',
                ($r['has_note'] || $r['has_answers']) ? 'Yes' : '—'
            );
        }
        echo '</tbody></table>';
    }

    printf(
        '<p class="sjs-muted" style="margin-top:24px">Reading tables with prefix <code>%s</code> in database <code>%s</code>.</p></div>',
        esc_html($wpdb->prefix . 'usp_'),
        esc_html(DB_NAME)
    );
}

function sjs_ago(string $mysql_time): string
{
    $ts = strtotime($mysql_time);
    if (!$ts || $ts < 86400) {
        return '—';
    }
    return human_time_diff($ts, time()) . ' ago';
}
