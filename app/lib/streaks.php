<?php
/**
 * Days, streaks, and the grace window.
 *
 * All dates are in each user's own timezone, so "today" flips at their local
 * midnight, not the server's. A day counts when it has at least one daily
 * entry: a clearing, a practice (conscious rest included), or gratitude.
 *
 * Streaks are recalculated from the days actually logged, rather than nudged
 * along entry by entry, so an entry logged for yesterday (see the grace window
 * below) fills its gap and the streak comes out right.
 *
 * Grace window: until noon, entries can be logged for yesterday. Late nights
 * happen; practice includes being kind to yourself about that.
 */

const USP_GRACE_HOUR = 12; // entries for yesterday are allowed before this local hour

function usp_zone(string $timezone): DateTimeZone
{
    try {
        return new DateTimeZone($timezone);
    } catch (Exception $e) {
        return new DateTimeZone('UTC');
    }
}

function usp_today(string $timezone): DateTimeImmutable
{
    return new DateTimeImmutable('today', usp_zone($timezone));
}

/** Whole days from $from to $to (both Y-m-d). 0 = same day, 1 = next day. */
function usp_days_between(string $from, string $to): int
{
    $a = new DateTimeImmutable($from);
    $b = new DateTimeImmutable($to);
    return (int) $a->diff($b)->format('%r%a');
}

/** True while the grace window for logging yesterday is open. */
function usp_can_log_yesterday(string $timezone, ?DateTimeImmutable $now = null): bool
{
    $now ??= new DateTimeImmutable('now', usp_zone($timezone));
    return (int) $now->format('G') < USP_GRACE_HOUR;
}

/** Distinct days with at least one entry, oldest first (Y-m-d). */
function usp_entry_dates(int $user_id): array
{
    global $wpdb;

    return $wpdb->get_col($wpdb->prepare(
        'SELECT DISTINCT entry_date FROM ' . usp_table('entries') . '
         WHERE user_id = %d ORDER BY entry_date',
        $user_id
    )) ?: [];
}

/**
 * Days, current streak, and longest streak from a sorted list of logged days.
 * The current streak stays alive through today: if you practiced yesterday
 * but haven't logged yet today, it still counts.
 */
function usp_compute_streaks(array $dates, string $today): array
{
    $longest = 0;
    $run     = 0;
    $prev    = null;

    foreach ($dates as $date) {
        $run     = ($prev !== null && usp_days_between($prev, $date) === 1) ? $run + 1 : 1;
        $longest = max($longest, $run);
        $prev    = $date;
    }

    $last    = $prev;
    $gap     = $last === null ? null : usp_days_between($last, $today);
    $current = ($gap === 0 || $gap === 1) ? $run : 0;

    return [
        'days'       => count($dates),
        'streak'     => $current,
        'longest'    => $longest,
        'done_today' => $gap === 0,
        'last_date'  => $last,
    ];
}

/** Current stats for a user, and keeps the profile's cached copy in sync. */
function usp_streak_stats(int $user_id): array
{
    global $wpdb;

    $profile = usp_get_profile($user_id);
    $stats   = usp_compute_streaks(usp_entry_dates($user_id), usp_today($profile['timezone'])->format('Y-m-d'));

    // Cached on the profile for the admin stats page; the entries are the source of truth.
    if ((int) $profile['current_streak'] !== $stats['streak']
        || (int) $profile['longest_streak'] !== $stats['longest']
        || $profile['last_checkin_date'] !== $stats['last_date']) {
        $wpdb->update(
            usp_table('profiles'),
            [
                'current_streak'    => $stats['streak'],
                'longest_streak'    => $stats['longest'],
                'last_checkin_date' => $stats['last_date'],
            ],
            ['user_id' => $user_id],
            ['%d', '%d', '%s'],
            ['%d']
        );
    }

    return $stats;
}
