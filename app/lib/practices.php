<?php
/**
 * Each person's practice list.
 *
 * Built-in practices come from content/prompts.php ('skills'). On top of
 * those, each person can:
 *   - hide a practice from their practice card (its reps and history stay),
 *   - give a practice their own name (e.g. Prayer → Devotionals),
 *   - add practices of their own. These are private to them, keyed "u<id>"
 *     in saved entries, and counted like any other practice.
 */

const USP_CUSTOM_PREFIX = 'u';

/**
 * This person's practices, built-ins first, then their own (oldest first).
 * Each: key, title (their name for it), base_title, description, custom,
 * hidden, plus any content options (note_placeholder, milestones).
 * $include_deleted keeps removed custom practices, so past entries still
 * have a name in History.
 */
function usp_user_skills(int $user_id, bool $include_deleted = false): array
{
    global $wpdb;

    $prefs = [];
    foreach ($wpdb->get_results($wpdb->prepare(
        'SELECT skill_key, hidden, label FROM ' . usp_table('practice_prefs') . ' WHERE user_id = %d',
        $user_id
    ), ARRAY_A) ?: [] as $row) {
        $prefs[$row['skill_key']] = $row;
    }

    $skills = [];
    foreach (usp_content()['skills'] ?? [] as $s) {
        $skills[] = $s + ['base_title' => $s['title'], 'custom' => false];
    }
    foreach ($wpdb->get_results($wpdb->prepare(
        'SELECT id, title, description, deleted FROM ' . usp_table('user_practices') . '
         WHERE user_id = %d' . ($include_deleted ? '' : ' AND deleted = 0') . ' ORDER BY id',
        $user_id
    ), ARRAY_A) ?: [] as $row) {
        $skills[] = [
            'key'         => USP_CUSTOM_PREFIX . $row['id'],
            'title'       => $row['title'],
            'base_title'  => $row['title'],
            'description' => (string) $row['description'],
            'custom'      => true,
            'deleted'     => (bool) $row['deleted'],
        ];
    }

    foreach ($skills as &$s) {
        $pref        = $prefs[$s['key']] ?? null;
        $s['hidden'] = (bool) ($pref['hidden'] ?? false);
        if (!empty($pref['label']) && !$s['custom']) {
            $s['title'] = $pref['label'];
        }
    }
    unset($s);

    return $skills;
}

/** This person's practices by key (including removed ones, for history). */
function usp_user_skills_by_key(int $user_id): array
{
    return array_column(usp_user_skills($user_id, true), null, 'key');
}

/** Show/hide a practice, or rename it ('' resets to its original name). */
function usp_save_practice_pref(int $user_id, array $in): ?string
{
    global $wpdb;

    $key   = (string) ($in['key'] ?? '');
    $skill = usp_user_skills_by_key($user_id)[$key] ?? null;
    if (!$skill || !empty($skill['deleted'])) {
        return 'not_found';
    }

    // Your own practices are renamed in place; built-ins get a personal label.
    if (array_key_exists('label', $in) && $skill['custom']) {
        $title = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $in['label'])), 0, 60);
        if ($title === '') {
            return 'empty';
        }
        $wpdb->update(usp_table('user_practices'), ['title' => $title], ['id' => (int) substr($key, 1), 'user_id' => $user_id]);
        unset($in['label']);
    }

    $current = $wpdb->get_row($wpdb->prepare(
        'SELECT hidden, label FROM ' . usp_table('practice_prefs') . ' WHERE user_id = %d AND skill_key = %s',
        $user_id,
        $key
    ), ARRAY_A) ?: ['hidden' => 0, 'label' => null];

    $hidden = array_key_exists('hidden', $in) ? (int) (bool) $in['hidden'] : (int) $current['hidden'];
    $label  = $current['label'];
    if (array_key_exists('label', $in)) {
        $label = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $in['label'])), 0, 60);
        $label = ($label === '' || $label === $skill['base_title']) ? null : $label;
    }

    $ok = $wpdb->replace(
        usp_table('practice_prefs'),
        ['user_id' => $user_id, 'skill_key' => $key, 'hidden' => $hidden, 'label' => $label],
        ['%d', '%s', '%d', '%s']
    );
    return $ok === false ? 'save_failed' : null;
}

/** Adds a practice of this person's own. Returns an error code, or null. */
function usp_add_practice(int $user_id, array $in): ?string
{
    global $wpdb;

    $title = mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($in['title'] ?? ''))), 0, 60);
    $desc  = mb_substr(trim((string) ($in['description'] ?? '')), 0, 300);
    if ($title === '') {
        return 'practice_name';
    }
    $ok = $wpdb->insert(usp_table('user_practices'), [
        'user_id'     => $user_id,
        'title'       => $title,
        'description' => $desc !== '' ? $desc : null,
    ], ['%d', '%s', '%s']);
    return $ok ? null : 'save_failed';
}

/** Removes one of this person's own practices. Its past entries stay in History. */
function usp_delete_practice(int $user_id, string $key): ?string
{
    global $wpdb;

    if (!str_starts_with($key, USP_CUSTOM_PREFIX) || !ctype_digit(substr($key, 1))) {
        return 'not_found';
    }
    $wpdb->update(usp_table('user_practices'), ['deleted' => 1], ['id' => (int) substr($key, 1), 'user_id' => $user_id]);
    return null;
}
