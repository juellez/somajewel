<?php
/**
 * Content helpers and the "state" the front end needs on load.
 * Content itself lives in content/prompts.php.
 */

function usp_content(): array
{
    static $content = null;
    return $content ??= (array) require __DIR__ . '/../content/prompts.php';
}

function usp_moods_by_key(): array
{
    return array_column(usp_content()['moods'], null, 'key');
}

function usp_skills_by_key(): array
{
    return array_column(usp_content()['skills'], null, 'key');
}

// ---------- weekly ----------

function usp_week_start(DateTimeImmutable $today): string
{
    return $today->modify('monday this week')->format('Y-m-d');
}

/** This week's question set, rotating by ISO week number. */
function usp_weekly_set(DateTimeImmutable $today): ?array
{
    $sets = usp_content()['weekly'] ?? [];
    if (!$sets) {
        return null;
    }
    return $sets[((int) $today->format('W')) % count($sets)];
}

/** Keys of this week's questions, as stored in weekly_responses.prompt_key. */
function usp_weekly_prompt_keys(?array $set): array
{
    if (!$set) {
        return [];
    }
    return array_map(fn ($q) => $set['key'] . ':' . $q['key'], $set['questions']);
}

function usp_weekly_state(int $user_id, DateTimeImmutable $today): ?array
{
    global $wpdb;

    $set = usp_weekly_set($today);
    if (!$set) {
        return null;
    }

    $week_start = usp_week_start($today);
    $rows       = $wpdb->get_results($wpdb->prepare(
        'SELECT prompt_key, response FROM ' . usp_table('weekly_responses') . '
         WHERE user_id = %d AND week_start = %s',
        $user_id,
        $week_start
    ), ARRAY_A) ?: [];
    $saved = array_column($rows, 'response', 'prompt_key');

    $questions = [];
    foreach ($set['questions'] as $q) {
        $key         = $set['key'] . ':' . $q['key'];
        $questions[] = [
            'key'      => $key,
            'text'     => $q['text'],
            'hint'     => $q['hint'] ?? '',
            'optional' => !empty($q['optional']),
            'response' => $saved[$key] ?? '',
        ];
    }

    return [
        'title'      => $set['title'],
        'intro'      => $set['intro'] ?? '',
        'week_start' => $week_start,
        'questions'  => $questions,
    ];
}

function usp_save_weekly_response(int $user_id, string $prompt_key, string $response): bool
{
    global $wpdb;

    $profile = usp_get_profile($user_id);
    $today   = usp_today($profile['timezone']);

    // Only this week's prompts can be saved.
    if (!in_array($prompt_key, usp_weekly_prompt_keys(usp_weekly_set($today)), true)) {
        return false;
    }

    return false !== $wpdb->query($wpdb->prepare(
        'INSERT INTO ' . usp_table('weekly_responses') . '
            (user_id, week_start, prompt_key, response) VALUES (%d, %s, %s, %s)
         ON DUPLICATE KEY UPDATE response = VALUES(response)',
        $user_id,
        usp_week_start($today),
        $prompt_key,
        mb_substr($response, 0, 20000)
    ));
}

// ---------- full state ----------

function usp_build_state(int $user_id): array
{
    $content = usp_content();
    $profile = usp_get_profile($user_id);
    $today   = usp_today($profile['timezone']);
    $reps    = usp_rep_counts($user_id);

    $skills = array_map(fn ($s) => $s + [
        'ring' => usp_ring($reps['skills'][$s['key']] ?? 0),
    ], $content['skills']);

    $declarations = array_map(fn ($d) => [
        'id'   => (int) $d['id'],
        'text' => $d['text'],
        'ring' => usp_ring($reps['declarations'][(int) $d['id']] ?? 0),
    ], usp_declarations($user_id));

    // Preselect the declaration practiced most recently.
    global $wpdb;
    $last_declaration = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT declaration_id FROM ' . usp_table('entries') . '
         WHERE user_id = %d AND declaration_id IS NOT NULL ORDER BY id DESC LIMIT 1',
        $user_id
    ));

    $rituals = [];
    foreach (USP_ENTRY_TYPES as $type) {
        $rituals[$type] = ['label' => ucfirst(USP_TYPE_LABELS[$type]['many'])]
            + ['ring' => usp_ring($reps['types'][$type] ?? 0)];
    }

    return [
        'user'             => ['name' => wp_get_current_user()->display_name],
        'today'            => $today->format('Y-m-d'),
        'timezone'         => $profile['timezone'],
        'stats'            => usp_streak_stats($user_id) + ['reps' => array_sum($reps['types'])],
        'today_counts'     => usp_today_counts($user_id, $today->format('Y-m-d')),
        'grace'            => [
            'open'      => usp_can_log_yesterday($profile['timezone']),
            'yesterday' => $today->modify('-1 day')->format('Y-m-d'),
            'until'     => USP_GRACE_HOUR === 12 ? 'noon' : sprintf('%d:00', USP_GRACE_HOUR),
        ],
        'moods'            => $content['moods'],
        'skills'           => $skills,
        'declarations'     => $declarations,
        'max_declarations' => USP_MAX_DECLARATIONS,
        'last_declaration' => $last_declaration ?: null,
        'rituals'          => $rituals,
        'clearing'         => $content['clearing'],
        'gratitude'        => $content['gratitude'],
        'rest'             => $content['rest'],
        'weekly'           => usp_weekly_state($user_id, $today),
    ];
}
