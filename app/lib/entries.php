<?php
/**
 * Daily entries and reps.
 *
 * Every entry is a rep: it adds to the count for its type (clearing,
 * practice, gratitude), its skill, and its declaration. Each count
 * fills a ring toward the next milestone (10, 20, 50, 100, then every 100),
 * celebrates, and starts the next ring fresh.
 */

// Conscious rest is a practice skill ('rest'), not its own type.
const USP_ENTRY_TYPES = ['clearing', 'practice', 'gratitude'];
const USP_MILESTONES  = [10, 20, 50, 100];

const USP_TYPE_LABELS = [
    'clearing'  => ['one' => 'clearing', 'many' => 'clearings'],
    'practice'  => ['one' => 'practice', 'many' => 'practices'],
    'gratitude' => ['one' => 'gratitude practice', 'many' => 'gratitude practices'],
];

// ---------- milestones ----------

function usp_next_milestone(int $count): int
{
    foreach (USP_MILESTONES as $m) {
        if ($count < $m) {
            return $m;
        }
    }
    return (intdiv($count, 100) + 1) * 100;
}

function usp_prev_milestone(int $count): int
{
    if ($count >= 100) {
        return intdiv($count, 100) * 100;
    }
    $prev = 0;
    foreach (USP_MILESTONES as $m) {
        if ($count >= $m) {
            $prev = $m;
        }
    }
    return $prev;
}

function usp_is_milestone(int $count): bool
{
    return $count > 0 && usp_prev_milestone($count) === $count;
}

/** Ring data for the front end: count, next milestone, and % of the way there. */
function usp_ring(int $count): array
{
    $prev = usp_prev_milestone($count);
    $next = usp_next_milestone($count);
    return [
        'count' => $count,
        'next'  => $next,
        'pct'   => (int) round(100 * ($count - $prev) / ($next - $prev)),
    ];
}

// ---------- counts ----------

function usp_rep_counts(int $user_id): array
{
    global $wpdb;

    $counts = ['types' => [], 'skills' => [], 'declarations' => []];
    $rows   = $wpdb->get_results($wpdb->prepare(
        'SELECT type, skill_key, declaration_id, COUNT(*) AS n FROM ' . usp_table('entries') . '
         WHERE user_id = %d GROUP BY type, skill_key, declaration_id',
        $user_id
    ), ARRAY_A) ?: [];

    foreach ($rows as $row) {
        $n = (int) $row['n'];
        $counts['types'][$row['type']] = ($counts['types'][$row['type']] ?? 0) + $n;
        if ($row['skill_key']) {
            $counts['skills'][$row['skill_key']] = ($counts['skills'][$row['skill_key']] ?? 0) + $n;
        }
        if ($row['declaration_id']) {
            $id = (int) $row['declaration_id'];
            $counts['declarations'][$id] = ($counts['declarations'][$id] ?? 0) + $n;
        }
    }

    return $counts;
}

/** Entries logged today, by type: ['practice' => 2, 'gratitude' => 1, ...] */
function usp_today_counts(int $user_id, string $today): array
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT type, COUNT(*) AS n FROM ' . usp_table('entries') . '
         WHERE user_id = %d AND entry_date = %s GROUP BY type',
        $user_id,
        $today
    ), ARRAY_A) ?: [];

    $counts = array_fill_keys(USP_ENTRY_TYPES, 0);
    foreach ($rows as $row) {
        $counts[$row['type']] = (int) $row['n'];
    }
    return $counts;
}

// ---------- creating entries ----------

/**
 * Validates and saves one entry and reports any milestones reached.
 * $in['for'] = 'yesterday' logs it for yesterday, allowed until noon.
 * Returns ['error' => code] or
 * ['celebrations' => [...], 'first_of_day' => bool, 'date' => Y-m-d, 'for_yesterday' => bool].
 */
function usp_create_entry(int $user_id, array $in): array
{
    global $wpdb;

    $type = (string) ($in['type'] ?? '');
    if (!in_array($type, USP_ENTRY_TYPES, true)) {
        return ['error' => 'bad_type'];
    }

    $declaration_id = (int) ($in['declaration_id'] ?? 0) ?: null;
    if ($declaration_id && !usp_user_owns_declaration($user_id, $declaration_id)) {
        return ['error' => 'bad_declaration'];
    }

    $mood = (string) ($in['mood'] ?? '');
    if ($mood !== '' && !isset(usp_moods_by_key()[$mood])) {
        return ['error' => 'bad_mood'];
    }
    $mood = $mood ?: null;

    $skill   = null;
    $answers = null;

    if ($type === 'practice') {
        $skill = (string) ($in['skill_key'] ?? '');
        if (!isset(usp_skills_by_key()[$skill])) {
            return ['error' => 'skill_required'];
        }
        if (!$declaration_id) {
            return ['error' => 'declaration_required'];
        }
        if (!$mood) {
            return ['error' => 'mood_required'];
        }
    }

    if ($type === 'clearing') {
        $given   = (array) ($in['answers'] ?? []);
        $answers = [];
        foreach (usp_content()['clearing']['questions'] as $q) {
            $value = trim((string) ($given[$q['key']] ?? ''));
            if ($value !== '') {
                $answers[$q['key']] = mb_substr($value, 0, 5000);
            }
        }
        if (!$answers) {
            return ['error' => 'empty'];
        }
    }

    $note    = mb_substr(trim((string) ($in['note'] ?? '')), 0, 5000);
    $profile = usp_get_profile($user_id);
    $today   = usp_today($profile['timezone']);

    $for_yesterday = ($in['for'] ?? 'today') === 'yesterday';
    if ($for_yesterday && !usp_can_log_yesterday($profile['timezone'])) {
        return ['error' => 'grace_closed'];
    }
    $date = ($for_yesterday ? $today->modify('-1 day') : $today)->format('Y-m-d');

    $first = !$wpdb->get_var($wpdb->prepare(
        'SELECT 1 FROM ' . usp_table('entries') . ' WHERE user_id = %d AND entry_date = %s LIMIT 1',
        $user_id,
        $date
    ));
    $before       = usp_rep_counts($user_id);
    $days_before  = usp_streak_stats($user_id);

    $saved = $wpdb->insert(
        usp_table('entries'),
        [
            'user_id'        => $user_id,
            'entry_date'     => $date,
            'type'           => $type,
            'declaration_id' => $declaration_id,
            'skill_key'      => $skill,
            'mood'           => $mood,
            'answers'        => $answers ? wp_json_encode($answers) : null,
            'note'           => $note !== '' ? $note : null,
        ],
        ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
    );

    if (!$saved) {
        return ['error' => 'save_failed'];
    }

    $after      = usp_rep_counts($user_id);
    $days_after = usp_streak_stats($user_id);

    return [
        'celebrations'  => usp_celebrations($before, $after, $type, $skill, $declaration_id, $days_before, $days_after),
        'first_of_day'  => $first,
        'date'          => $date,
        'for_yesterday' => $for_yesterday,
    ];
}

/** Messages for any milestone this entry just reached. */
function usp_celebrations(array $before, array $after, string $type, ?string $skill, ?int $declaration_id, array $days_before, array $days_after): array
{
    $out     = [];
    $crossed = fn (array $b, array $a, $key) =>
        ($a[$key] ?? 0) > ($b[$key] ?? 0) && usp_is_milestone($a[$key] ?? 0);

    if ($skill && $crossed($before['skills'], $after['skills'], $skill)) {
        $info  = usp_skills_by_key()[$skill];
        $out[] = isset($info['milestone'])
            ? sprintf('%d %s!', $after['skills'][$skill], $info['milestone'])
            : sprintf('%d %s reps!', $after['skills'][$skill], $info['title']);
    }

    if ($declaration_id && $crossed($before['declarations'], $after['declarations'], $declaration_id)) {
        $out[] = sprintf('%d reps of your declaration!', $after['declarations'][$declaration_id]);
    }

    if ($crossed($before['types'], $after['types'], $type)) {
        $out[] = sprintf('%d %s!', $after['types'][$type], USP_TYPE_LABELS[$type]['many']);
    }

    if ($days_after['days'] > $days_before['days'] && usp_is_milestone($days_after['days'])) {
        $out[] = sprintf('%d days of practice!', $days_after['days']);
    }

    if ($days_after['streak'] > $days_before['streak'] && usp_is_milestone($days_after['streak'])) {
        $out[] = sprintf('%d-day streak!', $days_after['streak']);
    }

    return $out;
}
