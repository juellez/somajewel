<?php
/**
 * Read-only history: a month calendar of logged days, a summary of any one
 * day, and past weekly reflections. Only ever returns the signed-in user's
 * own entries.
 */

const USP_SNIPPET_LENGTH = 140;

function usp_snippet(?string $text): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($text) > USP_SNIPPET_LENGTH
        ? rtrim(mb_substr($text, 0, USP_SNIPPET_LENGTH - 1)) . '…'
        : $text;
}

/** First month with any entry or weekly answer, as Y-m. */
function usp_first_month(int $user_id): ?string
{
    global $wpdb;

    $first = $wpdb->get_var($wpdb->prepare(
        'SELECT MIN(d) FROM (
            SELECT MIN(entry_date) AS d FROM ' . usp_table('entries') . ' WHERE user_id = %d
            UNION ALL
            SELECT MIN(week_key) FROM ' . usp_table('weekly_responses') . ' WHERE user_id = %d
         ) AS firsts',
        $user_id,
        $user_id
    ));
    return $first ? substr($first, 0, 7) : null;
}

/** Entry counts per day for one month (Y-m), plus how far back history goes. */
function usp_history_month(int $user_id, string $month): ?array
{
    global $wpdb;

    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        return null;
    }

    $profile = usp_get_profile($user_id);
    $today   = usp_today($profile['timezone']);
    $start   = $month . '-01';
    $end     = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT entry_date, COUNT(*) AS n FROM ' . usp_table('entries') . '
         WHERE user_id = %d AND entry_date BETWEEN %s AND %s GROUP BY entry_date',
        $user_id,
        $start,
        $end
    ), ARRAY_A) ?: [];

    return [
        'month'         => $month,
        'today'         => $today->format('Y-m-d'),
        'current_month' => $today->format('Y-m'),
        'first_month'   => usp_first_month($user_id) ?? $today->format('Y-m'),
        'days'          => (object) array_map('intval', array_column($rows, 'n', 'entry_date')),
    ];
}

/** Summaries of everything logged on one day (Y-m-d). */
function usp_history_day(int $user_id, string $date): ?array
{
    global $wpdb;

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT e.type, e.skill_key, e.mood, e.answers, e.note, e.declaration_id, d.text AS declaration
         FROM ' . usp_table('entries') . ' e
         LEFT JOIN ' . usp_table('declarations') . ' d ON d.id = e.declaration_id AND d.user_id = e.user_id
         WHERE e.user_id = %d AND e.entry_date = %s
         ORDER BY e.id',
        $user_id,
        $date
    ), ARRAY_A) ?: [];

    $content = usp_content();
    $skills  = usp_user_skills_by_key($user_id);
    $moods   = usp_moods_by_key();
    $titles  = [
        'clearing'  => $content['clearing']['title'],
        'practice'  => usp_ui('checklist.practice'),
        'gratitude' => $content['gratitude']['title'],
    ];

    $items = [];
    foreach ($rows as $row) {
        $title = $titles[$row['type']] ?? ucfirst($row['type']);
        if ($row['type'] === 'practice' && isset($skills[$row['skill_key']])) {
            $title .= ' · ' . $skills[$row['skill_key']]['title'];
        }

        $summary = '';
        if ($row['type'] === 'clearing' && $row['answers']) {
            $answers = json_decode($row['answers'], true) ?: [];
            $summary = isset($answers['stand'])
                ? 'Stand: ' . $answers['stand']
                : (string) reset($answers);
        }

        $mood    = $moods[$row['mood']] ?? null;
        $items[] = [
            'type'        => $row['type'],
            'title'       => $title,
            // For grouping a day's practices by declaration, then by skill.
            'skill'       => $skills[$row['skill_key']]['title'] ?? null,
            'decl_id'     => $row['declaration_id'] ? (int) $row['declaration_id'] : null,
            'decl_text'   => $row['declaration'] ?: null,
            'declaration' => $row['declaration'] ? usp_snippet($row['declaration']) : null,
            'mood'        => $mood ? ['key' => $row['mood'], 'icon' => $mood['icon'], 'label' => $mood['label']] : null,
            'summary'     => usp_snippet($summary),
            'note'        => usp_snippet($row['note']),
        ];
    }

    return ['date' => $date, 'items' => $items];
}

/** Past weekly reflections, newest first, with each answer in full. */
function usp_history_weekly(int $user_id): array
{
    global $wpdb;

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT week_key, prompt_key, response FROM ' . usp_table('weekly_responses') . "
         WHERE user_id = %d AND TRIM(response) <> ''
         ORDER BY week_key DESC, id",
        $user_id
    ), ARRAY_A) ?: [];

    // Question text by stored key ("set:question"); unknown keys show as-is.
    $labels = [];
    $order  = [];
    foreach (usp_content()['weekly'] ?? [] as $set) {
        foreach ($set['questions'] as $i => $q) {
            $labels[$set['key'] . ':' . $q['key']] = $q['text'];
            $order[$set['key'] . ':' . $q['key']]  = $i;
        }
    }

    $weeks = [];
    foreach ($rows as $row) {
        $weeks[$row['week_key']][] = [
            'question' => $labels[$row['prompt_key']] ?? $row['prompt_key'],
            'answer'   => $row['response'],
            '_order'   => $order[$row['prompt_key']] ?? 99,
        ];
    }

    // Shown as "Week of …" from this person's own week start.
    $dow = (int) (usp_get_profile($user_id)['week_start'] ?? 1);
    $out = [];
    foreach (array_slice($weeks, 0, 52, true) as $week_key => $answers) {
        usort($answers, fn ($a, $b) => $a['_order'] <=> $b['_order']);
        $out[] = [
            'week_key'   => $week_key,
            'week_start' => usp_week_start_from_key($week_key, $dow),
            'answers'    => array_map(fn ($a) => ['question' => $a['question'], 'answer' => $a['answer']], $answers),
        ];
    }

    return $out;
}
