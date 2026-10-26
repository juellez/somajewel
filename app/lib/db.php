<?php
/**
 * Database helpers. Uses WordPress's $wpdb connection, so no separate
 * credentials are needed. App tables are named {wp_prefix}usp_{name},
 * e.g. wp_usp_checkins.
 */

function usp_table(string $name): string
{
    global $wpdb;
    return $wpdb->prefix . 'usp_' . $name;
}

/**
 * Creates or updates the app's tables when USP_DB_VERSION increases.
 * The installed version is stored in the WordPress option usp_db_version.
 * To change the schema later: add a new numbered entry and bump USP_DB_VERSION.
 */
function usp_maybe_migrate(): void
{
    global $wpdb;

    $installed = (int) get_option('usp_db_version', 0);
    if ($installed >= USP_DB_VERSION) {
        return;
    }

    $charset = $wpdb->get_charset_collate();

    $migrations = [
        1 => [
            "CREATE TABLE IF NOT EXISTS " . usp_table('profiles') . " (
                user_id BIGINT UNSIGNED NOT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                current_streak INT UNSIGNED NOT NULL DEFAULT 0,
                longest_streak INT UNSIGNED NOT NULL DEFAULT 0,
                last_checkin_date DATE NULL,
                streak_freezes TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS " . usp_table('checkins') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                checkin_date DATE NOT NULL,
                practice_key VARCHAR(64) NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_day (user_id, checkin_date)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS " . usp_table('weekly_responses') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                week_start DATE NOT NULL,
                prompt_key VARCHAR(64) NOT NULL,
                response MEDIUMTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_week_prompt (user_id, week_start, prompt_key)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS " . usp_table('friendships') . " (
                user_id BIGINT UNSIGNED NOT NULL,
                friend_id BIGINT UNSIGNED NOT NULL,
                status ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, friend_id),
                KEY friend (friend_id)
            ) $charset",
        ],

        // v0.2: declarations and a general entries log (clearing, practice,
        // gratitude, rest). Replaces the v0.1 checkins table.
        2 => [
            "CREATE TABLE IF NOT EXISTS " . usp_table('declarations') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                text VARCHAR(500) NOT NULL,
                status ENUM('active','archived') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_status (user_id, status)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS " . usp_table('declaration_versions') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                declaration_id BIGINT UNSIGNED NOT NULL,
                text VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY declaration (declaration_id)
            ) $charset",

            "CREATE TABLE IF NOT EXISTS " . usp_table('entries') . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                entry_date DATE NOT NULL,
                type VARCHAR(20) NOT NULL,
                declaration_id BIGINT UNSIGNED NULL,
                skill_key VARCHAR(64) NULL,
                mood VARCHAR(32) NULL,
                answers MEDIUMTEXT NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_date (user_id, entry_date),
                KEY user_type (user_id, type)
            ) $charset",

            "DROP TABLE IF EXISTS " . usp_table('checkins'),
        ],

        // Conscious rest becomes a practice skill instead of its own entry type.
        3 => [
            "UPDATE " . usp_table('entries') . " SET type = 'practice', skill_key = 'rest' WHERE type = 'rest'",
        ],
    ];

    foreach ($migrations as $version => $queries) {
        if ($version <= $installed) {
            continue;
        }
        foreach ($queries as $sql) {
            if ($wpdb->query($sql) === false) {
                error_log("[usp] Migration $version failed: " . $wpdb->last_error);
                return; // stop; will retry on next request
            }
        }
        update_option('usp_db_version', $version, true);
    }
}

/**
 * Returns the user's app profile, creating it on first visit.
 */
function usp_get_profile(int $user_id): array
{
    global $wpdb, $usp_config;

    $table = usp_table('profiles');
    $query = $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id);
    $row   = $wpdb->get_row($query, ARRAY_A);

    if (!$row) {
        $wpdb->insert(
            $table,
            ['user_id' => $user_id, 'timezone' => $usp_config['default_timezone']],
            ['%d', '%s']
        );
        $row = $wpdb->get_row($query, ARRAY_A);
    }

    return $row ?: [];
}
