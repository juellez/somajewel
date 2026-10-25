<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Jewel Mlnarik · https://github.com/juellez/somajewel

/**
 * Streak logic. All dates are calculated in each user's own timezone,
 * so "today" flips at their local midnight, not the server's.
 *
 * A day counts toward the streak when it has at least one daily entry:
 * a clearing, a practice, gratitude, or conscious rest.
 *
 * Streak freezes: every USP_FREEZE_EVERY days of streak earns one freeze
 * (max USP_FREEZE_MAX). A missed day is covered automatically if a freeze
 * is available, and the freeze is spent at the next entry.
 */

const USP_FREEZE_EVERY = 7;
const USP_FREEZE_MAX   = 2;

function usp_today(string $timezone): DateTimeImmutable
{
    try {
        $zone = new DateTimeZone($timezone);
    } catch (Exception $e) {
        $zone = new DateTimeZone('UTC');
    }
    return new DateTimeImmutable('today', $zone);
}

/** Whole days from $from to $to (both Y-m-d). 0 = same day, 1 = next day. */
function usp_days_between(string $from, string $to): int
{
    $a = new DateTimeImmutable($from);
    $b = new DateTimeImmutable($to);
    return (int) $a->diff($b)->format('%r%a');
}

/**
 * Current streak status for display. Read-only: never changes the database.
 */
function usp_streak_status(array $profile): array
{
    $today   = usp_today($profile['timezone'])->format('Y-m-d');
    $last    = $profile['last_checkin_date'];
    $current = (int) $profile['current_streak'];
    $freezes = (int) $profile['streak_freezes'];

    if (!$last) {
        return [
            'streak'         => 0,
            'longest'        => 0,
            'done_today'     => false,
            'freezes'        => $freezes,
            'freezes_needed' => 0,
        ];
    }

    $gap    = usp_days_between($last, $today); // 0 = today, 1 = yesterday
    $missed = max(0, $gap - 1);
    $alive  = $missed <= $freezes;

    return [
        'streak'         => $alive ? $current : 0,
        'longest'        => (int) $profile['longest_streak'],
        'done_today'     => $gap <= 0,
        'freezes'        => $freezes,
        'freezes_needed' => $alive ? $missed : 0,
    ];
}

/**
 * Called after each daily entry. The first entry of a day advances the
 * streak; later entries that day change nothing.
 */
function usp_touch_streak(int $user_id): array
{
    global $wpdb;

    $profile = usp_get_profile($user_id);
    $today   = usp_today($profile['timezone'])->format('Y-m-d');
    $last    = $profile['last_checkin_date'];

    if ($last === $today) {
        return usp_streak_status($profile) + ['earned_freeze' => false];
    }

    $current = (int) $profile['current_streak'];
    $freezes = (int) $profile['streak_freezes'];
    $earned  = false;

    if (!$last) {
        $current = 1;
    } else {
        $gap    = usp_days_between($last, $today);
        $missed = max(0, $gap - 1);

        if ($gap <= 0) {
            // Clock or timezone moved backward; keep the streak as-is.
            $current = max($current, 1);
        } elseif ($missed <= $freezes) {
            $freezes -= $missed;
            $current += 1;
        } else {
            $current = 1;
        }
    }

    if ($current % USP_FREEZE_EVERY === 0 && $freezes < USP_FREEZE_MAX) {
        $freezes++;
        $earned = true;
    }

    $wpdb->update(
        usp_table('profiles'),
        [
            'current_streak'    => $current,
            'longest_streak'    => max((int) $profile['longest_streak'], $current),
            'last_checkin_date' => $today,
            'streak_freezes'    => $freezes,
        ],
        ['user_id' => $user_id],
        ['%d', '%d', '%s', '%d'],
        ['%d']
    );

    return usp_streak_status(usp_get_profile($user_id)) + ['earned_freeze' => $earned];
}
