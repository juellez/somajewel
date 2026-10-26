# SomaJewel

A small self-coaching and daily somatic practice app. Daily rituals with
streaks, weekly reflection questions, a history of your practice, and
voice-to-text answers.

It runs as a plain PHP app beside a WordPress site and uses WordPress only
for logins. Anyone with a WordPress account can use it.

## Requirements

- PHP 8.1+ (built and tested against 8.3)
- A WordPress site on the same domain (for logins and the database connection)
- HTTPS (browsers only allow the microphone on secure pages)

## Setup

1. Put this folder inside the WordPress root, so it's reachable at `/app`.
2. Optional: copy `config.sample.php` to `config.php` to change defaults.
3. Optional: add the app icon as `assets/icon-192.png` (and `assets/icon-512.png`).
4. Visit `/app`. You'll be sent to the WordPress login, then back.

Database tables (`{prefix}usp_*`) are created automatically on first visit.

## How it works

- **Tabs:** Today, Progress, and History along the bottom; the cog at the top
  shows who's signed in, with Log Out.
- **Today:** the counters (Days, Streak, Longest, Reps), a checklist of today's
  rituals with the practices named ("Centering ×3"), then the rituals
  themselves: morning clearing, practice (declaration + skill + mood check),
  and evening gratitude. Any one of them counts for the day. The practice card
  opens on arrival and stays open after logging, ready for the next rep. Each
  skill shows a teal badge with today's reps and its running total. Conscious
  Rest is a skill, so choosing rest with intention counts too.
- **Moods:** twelve, in a 4×3 grid, from lifted to settled to heavy. "Other"
  pairs with the optional note.
- **Progress:** the counters, then a ring per ritual and per skill.
- **Days, streak, longest, reps:** Days is every day you've practiced and only
  ever grows. Streak is consecutive days, and stays alive through today if you
  practiced yesterday. A missed day resets the streak but never Days or Reps.
- **Grace window:** until noon, anything can be logged for yesterday, for late
  nights. Streaks are recalculated from the days you actually logged, so a
  late entry fills its gap.
- **Reps:** every entry adds to its ritual, its skill, and its declaration.
  Ritual and skill counts each fill a ring toward their next milestone,
  celebrate, and start the next ring fresh. Days and streaks celebrate at the
  same milestones. Conscious Rest is counted (and counts toward the Practice
  ring) but has no milestones of its own. Declaration reps are counted but not
  shown yet.
- **Milestones:** 10, 20, 50, 100, 150, 300, 500, 750, 1,000, then every
  1,000. Set in `lib/entries.php` (`USP_MILESTONES`); a skill opts out with
  `'milestones' => false` in `content/prompts.php`.
- **History:** a calendar of practice days (tap a day for a read-only summary)
  and a list of past weekly reflections.
- **Weekly reflection:** seven questions, the last one optional.

## Editing content

Everything people see lives in `content/prompts.php`. Once a key is in use,
keep it; change the text freely.

## Files

| File | What it does |
|---|---|
| `index.php` | Login gate and page shell (header, settings menu, tab bar) |
| `api.php` | JSON endpoints: `state`, `entry`, `declaration`, `declaration_archive`, `weekly`, `history_month`, `history_day`, `history_weekly` |
| `bootstrap.php` | Loads config, WordPress, and the libraries |
| `lib/db.php` | Tables, migrations, user profiles |
| `lib/streaks.php` | Days, streaks, and the grace window (user's timezone) |
| `lib/entries.php` | Daily entries, reps, and milestone rings |
| `lib/history.php` | Month calendar, day summaries, weekly reflections |
| `lib/declarations.php` | Declarations (up to 3, with version history) |
| `lib/content.php` | Weekly questions and the page's state |
| `content/prompts.php` | Moods (with icons), skills (Conscious Rest first), clearing, gratitude, weekly questions |
| `assets/app.js` | Front end, including voice-to-text |
| `assets/app.css` | Kaleidoscope theme (light and dark mode) |
| `assets/icons.svg` | Phosphor icons (light, with a soft duotone tint; MIT) |
| `assets/fonts/` | Jost, hosted with the app (SIL Open Font License) |

## Voice-to-text

Uses the browser's built-in Web Speech API. Works in Chrome, Edge, and
Safari; the mic button is hidden in Firefox. Audio is processed by the
browser vendor (Google or Apple), not this server.

## Roadmap

- Settings: choose your timezone
- Export your history (for reviewing patterns, or with AI)
- Declaration milestones as an embodiment journey: 300 reps (muscle memory)
  to 3,000 (embodied), or a check-in after 100 reps under one declaration,
  possibly as a weekly reflection question
- Friends: see each other's streaks, send nudges
- Installable app (PWA) with daily push reminders
- v2: community share space
