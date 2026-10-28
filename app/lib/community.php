<?php
/**
 * Community: people who choose to share see each other's highlights.
 *
 * Two separate choices, both off until someone turns them on:
 *   share_progress      Days, Streak, Longest, Reps, and when they last practiced
 *   share_declarations  their active declarations
 * Either one puts them on the board under their display name, and lets them
 * see everyone else who shares. Nothing is shared until they've set a display
 * name; clearing it turns sharing off. Never shared: notes, moods, answers,
 * or which practices they do.
 */

const USP_DISPLAY_NAME_MAX = 40;

/** A suggested display name: their WordPress name, never an email address. Only ever a placeholder. */
function usp_default_display_name(int $user_id): string
{
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }
    $name = trim((string) ($user->display_name ?: $user->user_login));
    // Accounts made with an email as the username: keep only the part before @.
    if (str_contains($name, '@')) {
        $name = strstr($name, '@', true);
    }
    return mb_substr($name, 0, USP_DISPLAY_NAME_MAX);
}

/** This person's sharing choices, for the app's state. */
function usp_community_settings(int $user_id): array
{
    $profile = usp_get_profile($user_id);
    $named   = trim((string) ($profile['display_name'] ?? '')) !== '';
    return [
        // Without a name, nothing is shared, whatever the switches once said.
        'share_progress'     => $named && !empty($profile['share_progress']),
        'share_declarations' => $named && !empty($profile['share_declarations']),
        'display_name'       => (string) ($profile['display_name'] ?? ''),
        'default_name'       => usp_default_display_name($user_id),
    ];
}

/** Saves any of share_progress, share_declarations, display_name. Returns an error code, or null. */
function usp_save_community(int $user_id, array $in): ?string
{
    global $wpdb;

    $data = [];
    foreach (['share_progress', 'share_declarations'] as $key) {
        if (array_key_exists($key, $in)) {
            $data[$key] = $in[$key] ? 1 : 0;
        }
    }
    if (array_key_exists('display_name', $in)) {
        $name = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $in['display_name'])));
        $data['display_name'] = $name === '' ? null : mb_substr($name, 0, USP_DISPLAY_NAME_MAX);
    }
    if (!$data) {
        return 'empty';
    }

    // A display name comes first: no sharing without one, and clearing it
    // stops sharing.
    $profile = usp_get_profile($user_id);
    $name    = array_key_exists('display_name', $data) ? $data['display_name'] : ($profile['display_name'] ?? null);
    if ($name === null || $name === '') {
        if (!empty($data['share_progress']) || !empty($data['share_declarations'])) {
            return 'name_required';
        }
        $data['share_progress']     = 0;
        $data['share_declarations'] = 0;
    }

    $ok = $wpdb->update(usp_table('profiles'), $data, ['user_id' => $user_id]);
    return $ok === false ? 'save_failed' : null;
}

/**
 * Everyone who shares, for someone who shares. Those who share nothing get
 * ['sharing' => false] and no one else's data.
 * Order: practiced today, then most recent practice, then declarations-only.
 */
function usp_community_board(int $viewer_id): array
{
    global $wpdb;

    $mine = usp_community_settings($viewer_id);
    if (!$mine['share_progress'] && !$mine['share_declarations']) {
        return ['sharing' => false, 'people' => []];
    }

    $rows = $wpdb->get_results(
        'SELECT user_id, timezone, share_progress, share_declarations, display_name FROM ' . usp_table('profiles') . '
         WHERE (share_progress = 1 OR share_declarations = 1) AND display_name IS NOT NULL AND display_name <> \'\'',
        ARRAY_A
    ) ?: [];

    $people = [];
    foreach ($rows as $row) {
        $uid = (int) $row['user_id'];
        if (!get_userdata($uid)) {
            continue; // account deleted in WordPress
        }

        $person = [
            'name'         => $row['display_name'],
            'you'          => $uid === $viewer_id,
            'stats'        => null,
            'declarations' => null,
        ];

        if ($row['share_progress']) {
            $s     = usp_streak_stats($uid);
            $today = usp_today($row['timezone'])->format('Y-m-d');
            $person['stats'] = [
                'days'      => $s['days'],
                'streak'    => $s['streak'],
                'longest'   => $s['longest'],
                'reps'      => array_sum(usp_rep_counts($uid)['types']),
                'last_date' => $s['last_date'],
                // Relative to their own day, so time zones don't skew it.
                'last'      => $s['last_date'] === null ? null
                    : ($s['done_today'] ? 'today'
                    : (usp_days_between($s['last_date'], $today) === 1 ? 'yesterday' : 'earlier')),
            ];
        }
        if ($row['share_declarations']) {
            $person['declarations'] = array_column(usp_declarations($uid), 'text');
        }
        $people[] = $person;
    }

    $rank = fn ($p) => match ($p['stats']['last'] ?? null) {
        'today' => 0, 'yesterday' => 1, 'earlier' => 2, default => 3,
    };
    usort($people, fn ($a, $b) => [$rank($a), $b['stats']['last_date'] ?? '', mb_strtolower($a['name'])]
        <=> [$rank($b), $a['stats']['last_date'] ?? '', mb_strtolower($b['name'])]);

    return ['sharing' => true, 'people' => $people];
}
