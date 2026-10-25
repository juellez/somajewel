<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Jewel Mlnarik · https://github.com/juellez/somajewel

/**
 * Declarations: up to USP_MAX_DECLARATIONS active at once, in the user's
 * own words. Refining one keeps its reps and saves the earlier wording
 * in declaration_versions, so its evolution can be reviewed later.
 */

const USP_MAX_DECLARATIONS = 3;

function usp_declarations(int $user_id): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        'SELECT id, text FROM ' . usp_table('declarations') . "
         WHERE user_id = %d AND status = 'active' ORDER BY created_at, id",
        $user_id
    ), ARRAY_A) ?: [];
}

function usp_user_owns_declaration(int $user_id, int $id): bool
{
    global $wpdb;

    return (bool) $wpdb->get_var($wpdb->prepare(
        'SELECT 1 FROM ' . usp_table('declarations') . "
         WHERE id = %d AND user_id = %d AND status = 'active'",
        $id,
        $user_id
    ));
}

/**
 * Creates a declaration (no $id) or refines one. Returns an error code, or null on success.
 */
function usp_save_declaration(int $user_id, ?int $id, string $text): ?string
{
    global $wpdb;

    $text = mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 500);
    if ($text === '') {
        return 'empty';
    }

    if ($id) {
        if (!usp_user_owns_declaration($user_id, $id)) {
            return 'not_found';
        }
        $current = $wpdb->get_var($wpdb->prepare(
            'SELECT text FROM ' . usp_table('declarations') . ' WHERE id = %d',
            $id
        ));
        if ($current === $text) {
            return null;
        }
        $wpdb->update(usp_table('declarations'), ['text' => $text], ['id' => $id], ['%s'], ['%d']);
    } else {
        if (count(usp_declarations($user_id)) >= USP_MAX_DECLARATIONS) {
            return 'limit';
        }
        $wpdb->insert(usp_table('declarations'), ['user_id' => $user_id, 'text' => $text], ['%d', '%s']);
        $id = (int) $wpdb->insert_id;
        if (!$id) {
            return 'save_failed';
        }
    }

    $wpdb->insert(usp_table('declaration_versions'), ['declaration_id' => $id, 'text' => $text], ['%d', '%s']);
    return null;
}

/** Archiving hides a declaration but keeps its history and reps. */
function usp_archive_declaration(int $user_id, int $id): bool
{
    global $wpdb;

    if (!usp_user_owns_declaration($user_id, $id)) {
        return false;
    }
    return false !== $wpdb->update(
        usp_table('declarations'),
        ['status' => 'archived'],
        ['id' => $id],
        ['%s'],
        ['%d']
    );
}
