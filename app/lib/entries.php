<?php
/**
 * Daily entries and reps.
 *
 * Every entry is a rep: it adds to the count for its type (clearing,
 * practice, gratitude), its skill, and its declaration. Type and skill
 * counts fill a ring toward the next milestone (USP_MILESTONES, then every
 * 1,000), celebrate, and start the next ring fresh.
 *
 * A skill can opt out with 'milestones' => false (conscious rest does).
 *
 * Declarations have their own two goals: about 300 reps builds muscle memory,
 * about 3,000 embodiment (USP_DECLARATION_GOALS).
 */

// Conscious rest is a practice skill ('rest'), not its own type.
const USP_ENTRY_TYPES = ['clearing', 'practice', 'gratitude'];
const USP_DAILY_RITUALS = ['clearing', 'gratitude']; // once per day when logged late (practices have no limit)
const USP_MILESTONES  = [10, 20, 50, 100, 150, 300, 500, 750, 1000];
const USP_MILESTONE_STEP = 1000; // after the last one, every 1,000

const USP_DECLARATION_GOALS = [300, 3000]; // muscle memory, then embodiment

// ---------- milestones ----------

function usp_next_milestone(int $count): int
{
    foreach (USP_MILESTONES as $m) {
        if ($count < $m) {
            return $m;
        }
    }
    return (intdiv($count, USP_MILESTONE_STEP) + 1) * USP_MILESTONE_STEP;
}

function usp_prev_milestone(int $count): int
{
    if ($count >= USP_MILESTONE_STEP) {
        return intdiv($count, USP_MILESTONE_STEP) * USP_MILESTONE_STEP;
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
        'pct'   => (int) floor(100 * ($count - $prev) / ($next - $prev)), // never a full ring before the milestone
    ];
}

/** A declaration's progress: toward 300, then 3,000, then embodied. */
function usp_declaration_progress(int $count): array
{
    foreach (USP_DECLARATION_GOALS as $goal) {
        if ($count < $goal) {
            return ['count' => $count, 'goal' => $goal, 'pct' => (int) floor(100 * $count / $goal), 'embodied' => false];
        }
    }
    return ['count' => $count, 'goal' => null, 'pct' => 100, 'embodied' => true];
}

// ---------- counts ----------

function usp_rep_counts(int $user_id): array
{
    global $wpdb;

    // decl_skills: reps per skill under each declaration, [id => [skill => n]].
    $counts = ['types' => [], 'skills' => [], 'declarations' => [], 'decl_skills' => []];
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
            if ($row['skill_key']) {
                $counts['decl_skills'][$id][$row['skill_key']] = $n;
            }
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

/** Which once-a-day rituals already have an entry on a date: ['clearing', ...] */
function usp_rituals_logged(int $user_id, string $date): array
{
    $counts = usp_today_counts($user_id, $date);
    return array_values(array_filter(USP_DAILY_RITUALS, fn ($type) => $counts[$type] > 0));
}

/**
 * Practices logged today: by skill, and by skill under each declaration.
 * ['skills' => ['centering' => 3], 'declarations' => [12 => ['centering' => 2]]]
 */
function usp_today_skill_counts(int $user_id, string $today): array
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT declaration_id, skill_key, COUNT(*) AS n FROM ' . usp_table('entries') . "
         WHERE user_id = %d AND entry_date = %s AND type = 'practice' AND skill_key IS NOT NULL
         GROUP BY declaration_id, skill_key",
        $user_id,
        $today
    ), ARRAY_A) ?: [];

    $out = ['skills' => [], 'declarations' => []];
    foreach ($rows as $row) {
        $n = (int) $row['n'];
        $out['skills'][$row['skill_key']] = ($out['skills'][$row['skill_key']] ?? 0) + $n;
        if ($row['declaration_id']) {
            $out['declarations'][(int) $row['declaration_id']][$row['skill_key']] = $n;
        }
    }
    return $out;
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
        $info = usp_user_skills_by_key($user_id)[$skill] ?? null;
        if (!$info || !empty($info['deleted'])) {
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
        $answers = usp_clearing_answers($in['answers'] ?? []);
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

    // Rituals are once a day: today's is edited (usp_update_ritual), and a
    // late one for yesterday can't add a second.
    if (in_array($type, USP_DAILY_RITUALS, true) && in_array($type, usp_rituals_logged($user_id, $date), true)) {
        return ['error' => $for_yesterday ? 'already_logged' : 'already_today'];
    }

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
        'celebrations'  => usp_celebrations($user_id, $before, $after, $type, $skill, $declaration_id, $days_before, $days_after),
        'first_of_day'  => $first,
        'date'          => $date,
        'for_yesterday' => $for_yesterday,
    ];
}

/** Clearing answers by question key, trimmed; empty ones left out. */
function usp_clearing_answers($given): array
{
    $given   = (array) $given;
    $answers = [];
    foreach (usp_content()['clearing']['questions'] as $q) {
        $value = trim((string) ($given[$q['key']] ?? ''));
        if ($value !== '') {
            $answers[$q['key']] = mb_substr($value, 0, 5000);
        }
    }
    return $answers;
}

/**
 * Today's clearing and gratitude, if logged: ['clearing' => [id, answers,
 * mood, note] or null, 'gratitude' => ...]. If an older day somehow has two,
 * the latest is the one shown and edited.
 */
function usp_today_rituals(int $user_id, string $today): array
{
    global $wpdb;

    $out = array_fill_keys(USP_DAILY_RITUALS, null);
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT id, type, answers, mood, note FROM ' . usp_table('entries') . "
         WHERE user_id = %d AND entry_date = %s AND type IN ('clearing', 'gratitude')
         ORDER BY id",
        $user_id,
        $today
    ), ARRAY_A) ?: [];
    foreach ($rows as $row) {
        $out[$row['type']] = [
            'id'      => (int) $row['id'],
            'answers' => $row['answers'] ? (object) (json_decode($row['answers'], true) ?: []) : (object) [],
            'mood'    => $row['mood'] ?: '',
            'note'    => (string) $row['note'],
        ];
    }
    return $out;
}

/**
 * Edits today's clearing or gratitude: $in = ['id', 'answers'? (clearing),
 * 'mood'?, 'note'?]. Only your own, and only today's. Returns an error code, or null.
 */
function usp_update_ritual(int $user_id, array $in): ?string
{
    global $wpdb;

    $today = usp_today(usp_get_profile($user_id)['timezone'])->format('Y-m-d');
    $entry = $wpdb->get_row($wpdb->prepare(
        'SELECT id, type FROM ' . usp_table('entries') . ' WHERE id = %d AND user_id = %d AND entry_date = %s',
        (int) ($in['id'] ?? 0),
        $user_id,
        $today
    ), ARRAY_A);
    if (!$entry || !in_array($entry['type'], USP_DAILY_RITUALS, true)) {
        return 'not_found';
    }

    $mood = (string) ($in['mood'] ?? '');
    if ($mood !== '' && !isset(usp_moods_by_key()[$mood])) {
        return 'bad_mood';
    }
    $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 5000);
    $data = ['mood' => $mood ?: null, 'note' => $note !== '' ? $note : null];

    if ($entry['type'] === 'clearing') {
        $answers = usp_clearing_answers($in['answers'] ?? []);
        if (!$answers) {
            return 'empty';
        }
        $data['answers'] = wp_json_encode($answers);
    }

    return $wpdb->update(usp_table('entries'), $data, ['id' => (int) $entry['id']]) === false ? 'save_failed' : null;
}

/** Messages for any milestone this entry just reached. */
function usp_celebrations(int $user_id, array $before, array $after, string $type, ?string $skill, ?int $declaration_id, array $days_before, array $days_after): array
{
    $out     = [];
    $crossed = fn (array $b, array $a, $key) =>
        ($a[$key] ?? 0) > ($b[$key] ?? 0) && usp_is_milestone($a[$key] ?? 0);

    $info = $skill ? (usp_user_skills_by_key($user_id)[$skill] ?? null) : null;
    if ($info && ($info['milestones'] ?? true) && $crossed($before['skills'], $after['skills'], $skill)) {
        $out[] = usp_ui('celebrations.skill', ['n' => $after['skills'][$skill], 'name' => $info['title']]);
    }

    if ($declaration_id) {
        $n = $after['declarations'][$declaration_id] ?? 0;
        if ($n > ($before['declarations'][$declaration_id] ?? 0) && in_array($n, USP_DECLARATION_GOALS, true)) {
            $key   = $n === USP_DECLARATION_GOALS[0] ? 'declaration' : 'declaration_3k';
            $out[] = usp_ui("celebrations.$key", ['n' => number_format($n)]);
        }
    }

    if ($crossed($before['types'], $after['types'], $type)) {
        $out[] = usp_ui('celebrations.ritual', ['n' => $after['types'][$type], 'name' => usp_ui("celebrations.ritual_names.$type")]);
    }

    if ($days_after['days'] > $days_before['days'] && usp_is_milestone($days_after['days'])) {
        $out[] = usp_ui('celebrations.days', ['n' => $days_after['days']]);
    }

    if ($days_after['streak'] > $days_before['streak'] && usp_is_milestone($days_after['streak'])) {
        $out[] = usp_ui('celebrations.streak', ['n' => $days_after['streak']]);
    }

    return $out;
}
