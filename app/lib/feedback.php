<?php
/**
 * Feedback from testers: something that's off, an idea, or a vote on the
 * app's name. Saved with the screen they were on, their browser, and the
 * app version, and read in WordPress admin (SomaJewel Stats → Feedback).
 */

const USP_FEEDBACK_KINDS = ['issue', 'idea', 'naming'];

/** Names in the poll, from content (ui → feedback → poll_options). */
function usp_poll_options(): array
{
    return array_values(array_filter((array) (usp_content()['ui']['feedback']['poll_options'] ?? []), 'is_string'));
}

/** Returns an error code, or null when saved. */
function usp_save_feedback(int $user_id, array $in): ?string
{
    global $wpdb;

    $kind = (string) ($in['kind'] ?? '');
    if (!in_array($kind, USP_FEEDBACK_KINDS, true)) {
        return 'bad_kind';
    }

    $message = mb_substr(trim((string) ($in['message'] ?? '')), 0, 5000);
    $vote    = (string) ($in['vote'] ?? '');
    $other   = mb_substr(trim((string) ($in['other'] ?? '')), 0, 100);
    if ($vote !== 'other' && !in_array($vote, usp_poll_options(), true)) {
        $vote = '';
    }
    if ($vote === 'other' && $other === '') {
        $vote = '';
    }
    if ($message === '' && $vote === '') {
        return 'feedback_empty';
    }
    if ($message === '' && $vote !== '') {
        $kind = 'naming'; // just a vote
    }

    $saved = $wpdb->insert(usp_table('feedback'), [
        'user_id'    => $user_id,
        'kind'       => $kind,
        'message'    => $message !== '' ? $message : null,
        'name_vote'  => $vote !== '' ? $vote : null,
        'name_other' => $vote === 'other' ? $other : null,
        'screen'     => mb_substr(preg_replace('/[^a-z_-]/', '', (string) ($in['screen'] ?? '')), 0, 32) ?: null,
        'device'     => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
        'version'    => usp_build()['label'],
    ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

    return $saved ? null : 'save_failed';
}

/** This person's latest name vote: ['vote' => 'SomaRosa', 'other' => ''] or null. */
function usp_my_vote(int $user_id): ?array
{
    global $wpdb;

    $row = $wpdb->get_row($wpdb->prepare(
        'SELECT name_vote, name_other FROM ' . usp_table('feedback') . '
         WHERE user_id = %d AND name_vote IS NOT NULL ORDER BY id DESC LIMIT 1',
        $user_id
    ), ARRAY_A);

    return $row ? ['vote' => $row['name_vote'], 'other' => (string) $row['name_other']] : null;
}
