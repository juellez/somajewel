<?php
/**
 * Declarations: up to USP_MAX_DECLARATIONS active at once, in the user's
 * own words. Refining one keeps its reps and saves the earlier wording
 * in declaration_versions, so its evolution can be reviewed later.
 *
 * Retiring one takes it off the practice card but keeps its reps and
 * history; it can be reactivated when there's room. (In the database a
 * retired declaration has status 'archived', its original name.)
 */

const USP_MAX_DECLARATIONS = 3;

const USP_RETIRED = 'archived'; // database status for a retired declaration

/** Active declarations, oldest first. Pass USP_RETIRED for retired ones (newest first). */
function usp_declarations(int $user_id, string $status = 'active'): array
{
    global $wpdb;

    $order = $status === 'active' ? 'created_at, id' : 'updated_at DESC, id DESC';
    return $wpdb->get_results($wpdb->prepare(
        'SELECT id, text FROM ' . usp_table('declarations') . "
         WHERE user_id = %d AND status = %s ORDER BY $order",
        $user_id,
        $status
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

/** Retiring takes a declaration off the practice card; its reps and history stay. */
function usp_retire_declaration(int $user_id, int $id): bool
{
    global $wpdb;

    if (!usp_user_owns_declaration($user_id, $id)) {
        return false;
    }
    return false !== $wpdb->update(
        usp_table('declarations'),
        ['status' => USP_RETIRED],
        ['id' => $id],
        ['%s'],
        ['%d']
    );
}

/** Brings a retired declaration back, if there's room. Returns an error code, or null. */
function usp_reactivate_declaration(int $user_id, int $id): ?string
{
    global $wpdb;

    $owned = $wpdb->get_var($wpdb->prepare(
        'SELECT 1 FROM ' . usp_table('declarations') . ' WHERE id = %d AND user_id = %d AND status = %s',
        $id,
        $user_id,
        USP_RETIRED
    ));
    if (!$owned) {
        return 'not_found';
    }
    if (count(usp_declarations($user_id)) >= USP_MAX_DECLARATIONS) {
        return 'limit';
    }
    $wpdb->update(usp_table('declarations'), ['status' => 'active'], ['id' => $id], ['%s'], ['%d']);
    return null;
}
