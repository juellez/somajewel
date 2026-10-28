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

// ---------- screen text ----------

/** Fills {name}-style placeholders. */
function usp_fill(string $text, array $vars = []): string
{
    return strtr($text, array_combine(array_map(fn ($k) => '{' . $k . '}', array_keys($vars)), array_map('strval', $vars)) ?: []);
}

/** Screen text from content/prompts.php 'ui', by dotted path: usp_ui('nav.today'). */
function usp_ui(string $path, array $vars = []): string
{
    $value = usp_content()['ui'] ?? [];
    foreach (explode('.', $path) as $key) {
        $value = is_array($value) ? ($value[$key] ?? null) : null;
    }
    return is_string($value) ? usp_fill($value, $vars) : $path;
}

// ---------- icon style test (beta) ----------

/**
 * Which icon style this person sees: 'a' or 'b'. Stable per person (by user
 * ID), unless they switched in the settings menu (saved in a cookie).
 */
function usp_icon_variant(int $user_id): string
{
    $test = usp_content()['ui']['icon_test'] ?? [];
    if (!isset($test['b'])) {
        return 'a';
    }
    $chosen = $_COOKIE['sj_icons'] ?? '';
    if ($chosen === 'a' || $chosen === 'b') {
        return $chosen;
    }
    return $user_id % 2 ? 'b' : 'a';
}

/** The app's name: the content default, or the one this person is trying (beta). */
function usp_app_name(): string
{
    $ui      = usp_content()['ui'] ?? [];
    $options = (array) ($ui['name_test']['options'] ?? []);
    $chosen  = (string) ($_COOKIE['sj_name'] ?? '');
    return in_array($chosen, $options, true) ? $chosen : (string) ($ui['app']['name'] ?? 'Soma Rosa');
}

/** Icons for this person's style: ['progress' => …, 'sacred' => …]. */
function usp_variant_icons(int $user_id): array
{
    $test = usp_content()['ui']['icon_test'] ?? [];
    return $test[usp_icon_variant($user_id)] ?? ['progress' => 'chart-donut'];
}

// ---------- weekly ----------
//
// Each week's reflection opens near the end of the week (Friday, set in
// content: ui → reflect → opens_on) and stays open, and editable, until the
// next one opens. So on a Tuesday you're still finishing last week's; on
// Friday this week's begins and last week's moves to History.
//
// Answers are saved under the week's Thursday (week_key), the day ISO uses to
// assign a week to its year. Thursday falls in the same week whether weeks
// start on Sunday or Monday, so changing that setting never splits a week.
// What people see, "Week of Oct 19", follows their own week start.

/** The first day of the week holding $date, for a week starting on $start_dow (0 = Sun, 1 = Mon). */
function usp_week_start(DateTimeImmutable $date, int $start_dow = 1): string
{
    $back = ((int) $date->format('w') - $start_dow + 7) % 7;
    return $date->modify("-$back days")->format('Y-m-d');
}

/** The Thursday of the week holding $date: the week's key. */
function usp_week_key(DateTimeImmutable $date, int $start_dow = 1): string
{
    $start = new DateTimeImmutable(usp_week_start($date, $start_dow));
    return $start->modify('+' . ((4 - $start_dow + 7) % 7) . ' days')->format('Y-m-d');
}

/** A week's first day (for display), from its key and the person's week start. */
function usp_week_start_from_key(string $week_key, int $start_dow = 1): string
{
    return (new DateTimeImmutable($week_key))->modify('-' . ((4 - $start_dow + 7) % 7) . ' days')->format('Y-m-d');
}

/** The week whose reflection is open today. */
function usp_reflection_week(DateTimeImmutable $today, int $start_dow): array
{
    $opens  = (int) (usp_content()['ui']['reflect']['opens_on'] ?? 5);
    $since  = ((int) $today->format('w') - $opens + 7) % 7;   // days since it last opened
    $opened = $today->modify("-$since days");
    return [
        'week_key'   => usp_week_key($opened, $start_dow),
        'week_start' => usp_week_start($opened, $start_dow),
        'this_week'  => usp_week_key($opened, $start_dow) === usp_week_key($today, $start_dow),
        'next_opens' => $opened->modify('+7 days')->format('Y-m-d'),
    ];
}

/** The question set for a week, rotating by its week number. */
function usp_weekly_set(string $week_key): ?array
{
    $sets = usp_content()['weekly'] ?? [];
    if (!$sets) {
        return null;
    }
    $week = (int) (new DateTimeImmutable($week_key))->format('W');
    return $sets[$week % count($sets)];
}

/** Keys of a set's questions, as stored in weekly_responses.prompt_key. */
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

    $profile = usp_get_profile($user_id);
    $week    = usp_reflection_week($today, (int) ($profile['week_start'] ?? 1));
    $set     = usp_weekly_set($week['week_key']);
    if (!$set) {
        return null;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT prompt_key, response FROM ' . usp_table('weekly_responses') . '
         WHERE user_id = %d AND week_key = %s',
        $user_id,
        $week['week_key']
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
        'week_key'   => $week['week_key'],
        'week_start' => $week['week_start'],
        'this_week'  => $week['this_week'],
        'next_opens' => $week['next_opens'],
        'questions'  => $questions,
    ];
}

/** Saves an answer to the reflection that's open now (and only that one). */
function usp_save_weekly_response(int $user_id, string $prompt_key, string $response): bool
{
    global $wpdb;

    $profile = usp_get_profile($user_id);
    $week    = usp_reflection_week(usp_today($profile['timezone']), (int) ($profile['week_start'] ?? 1));

    if (!in_array($prompt_key, usp_weekly_prompt_keys(usp_weekly_set($week['week_key'])), true)) {
        return false;
    }

    return false !== $wpdb->query($wpdb->prepare(
        'INSERT INTO ' . usp_table('weekly_responses') . '
            (user_id, week_key, prompt_key, response) VALUES (%d, %s, %s, %s)
         ON DUPLICATE KEY UPDATE response = VALUES(response)',
        $user_id,
        $week['week_key'],
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

    // Skills without milestones get a plain count and no ring.
    $skills = array_map(function ($s) use ($reps) {
        $count = $reps['skills'][$s['key']] ?? 0;
        return $s + [
            'count' => $count,
            'ring'  => ($s['milestones'] ?? true) ? usp_ring($count) : null,
        ];
    }, $content['skills']);

    $today_reps = usp_today_skill_counts($user_id, $today->format('Y-m-d'));

    // Each declaration with its progress toward 300 / 3,000 and the skills
    // practiced under it (most reps first), counted for this declaration only.
    $titles  = array_column($content['skills'], 'title', 'key');
    $declare = function (array $d) use ($reps, $today_reps, $titles) {
        $id     = (int) $d['id'];
        $skills = [];
        foreach ($reps['decl_skills'][$id] ?? [] as $key => $n) {
            if (isset($titles[$key])) {
                $skills[] = ['key' => $key, 'title' => $titles[$key], 'count' => $n, 'today' => $today_reps['declarations'][$id][$key] ?? 0];
            }
        }
        usort($skills, fn ($a, $b) => $b['count'] <=> $a['count']);
        return [
            'id'       => $id,
            'text'     => $d['text'],
            'progress' => usp_declaration_progress($reps['declarations'][$id] ?? 0),
            'skills'   => $skills,
        ];
    };
    $declarations = array_map($declare, usp_declarations($user_id));
    $retired      = array_map($declare, usp_declarations($user_id, USP_RETIRED));

    // Moods, with this person's icon style applied (beta test).
    $icons = usp_variant_icons($user_id);
    $moods = array_map(fn ($m) => isset($icons[$m['key']]) ? ['icon' => $icons[$m['key']]] + $m : $m, $content['moods']);

    // Preselect the declaration practiced most recently.
    global $wpdb;
    $last_declaration = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT declaration_id FROM ' . usp_table('entries') . '
         WHERE user_id = %d AND declaration_id IS NOT NULL ORDER BY id DESC LIMIT 1',
        $user_id
    ));

    $rituals = [];
    foreach (USP_ENTRY_TYPES as $type) {
        $rituals[$type] = ['ring' => usp_ring($reps['types'][$type] ?? 0)];
    }

    return [
        'user'             => ['name' => wp_get_current_user()->display_name],
        'today'            => $today->format('Y-m-d'),
        'timezone'         => $profile['timezone'],
        'timezone_set'     => (bool) ($profile['timezone_set'] ?? false),
        'week_start'       => (int) ($profile['week_start'] ?? 1),
        'app_name'         => usp_app_name(),
        'build'            => usp_build(),
        'my_vote'          => usp_my_vote($user_id),
        'stats'            => usp_streak_stats($user_id) + ['reps' => array_sum($reps['types'])],
        'today_counts'     => usp_today_counts($user_id, $today->format('Y-m-d')),
        'today_skills'     => (object) $today_reps['skills'],
        'grace'            => [
            'open'      => usp_can_log_yesterday($profile['timezone']),
            'yesterday' => $today->modify('-1 day')->format('Y-m-d'),
            'until'     => USP_GRACE_HOUR === 12 ? 'noon' : sprintf('%d:00', USP_GRACE_HOUR),
        ],
        'moods'            => $moods,
        'skills'           => $skills,
        'declarations'     => $declarations,
        'retired'          => $retired,
        'max_declarations' => USP_MAX_DECLARATIONS,
        'last_declaration' => $last_declaration ?: null,
        'rituals'          => $rituals,
        'clearing'         => $content['clearing'],
        'gratitude'        => $content['gratitude'],
        'weekly'           => usp_weekly_state($user_id, $today),
        'ui'               => $content['ui'],
        'icon_variant'     => usp_icon_variant($user_id),
        'icon_test'        => isset($content['ui']['icon_test']['b']),
    ];
}
