<?php
/**
 * JSON API for the front end.
 *   GET  api.php?action=state                → everything the page needs
 *   POST api.php?action=entry                → { type, declaration_id?, skill_key?, mood?, note?, answers?, for? }
 *                                               (for: "yesterday" logs it for yesterday, until noon)
 *   POST api.php?action=declaration          → { id?, text }  (create, or refine when id is given)
 *   POST api.php?action=declaration_retire   → { id }
 *   POST api.php?action=declaration_reactivate → { id }
 *   POST api.php?action=weekly               → { key, response }
 *   GET  api.php?action=history_month&month=Y-m   → entry counts per day
 *   GET  api.php?action=history_day&date=Y-m-d    → summaries of that day's entries
 *   GET  api.php?action=history_weekly            → past weekly reflections
 * Requires a WordPress login and the X-USP-Nonce header.
 */

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function usp_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo wp_json_encode($data);
    exit;
}

if (!is_user_logged_in()) {
    usp_json(['error' => 'not_logged_in'], 401);
}

if (!wp_verify_nonce($_SERVER['HTTP_X_USP_NONCE'] ?? '', 'usp_api')) {
    usp_json(['error' => 'bad_nonce'], 403);
}

$user_id = get_current_user_id();
$action  = $_GET['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];
$input   = json_decode(file_get_contents('php://input') ?: '[]', true);
$input   = is_array($input) ? $input : [];

switch ("$method $action") {

    case 'GET state':
        usp_json(usp_build_state($user_id));

    case 'POST entry':
        $result = usp_create_entry($user_id, $input);
        if (isset($result['error'])) {
            usp_json($result, 400);
        }
        usp_json($result + ['state' => usp_build_state($user_id)]);

    case 'POST declaration':
        $error = usp_save_declaration($user_id, (int) ($input['id'] ?? 0) ?: null, (string) ($input['text'] ?? ''));
        $error ? usp_json(['error' => $error], 400) : usp_json(['state' => usp_build_state($user_id)]);

    case 'POST declaration_reactivate':
        $error = usp_reactivate_declaration($user_id, (int) ($input['id'] ?? 0));
        $error ? usp_json(['error' => $error], 400) : usp_json(['state' => usp_build_state($user_id)]);

    case 'POST declaration_archive': // older name, kept for any open pages
    case 'POST declaration_retire':
        usp_retire_declaration($user_id, (int) ($input['id'] ?? 0))
            ? usp_json(['state' => usp_build_state($user_id)])
            : usp_json(['error' => 'not_found'], 404);

    case 'POST weekly':
        $ok = usp_save_weekly_response(
            $user_id,
            (string) ($input['key'] ?? ''),
            (string) ($input['response'] ?? '')
        );
        $ok ? usp_json(['saved' => true]) : usp_json(['error' => 'save_failed'], 400);

    case 'GET history_month':
        $month = usp_history_month($user_id, (string) ($_GET['month'] ?? ''));
        $month ? usp_json($month) : usp_json(['error' => 'bad_month'], 400);

    case 'GET history_day':
        $day = usp_history_day($user_id, (string) ($_GET['date'] ?? ''));
        $day ? usp_json($day) : usp_json(['error' => 'bad_date'], 400);

    case 'GET history_weekly':
        usp_json(['weeks' => usp_history_weekly($user_id)]);

    default:
        usp_json(['error' => 'unknown_action'], 404);
}
