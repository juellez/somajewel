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

- **Today:** a counter header (Days, Streak, Longest, Reps), then the daily
  rituals: morning clearing, practice (declaration + skill + after-mood), and
  evening gratitude. Any one of them counts for the day. Conscious rest is a
  practice skill, so choosing rest with intention counts too.
- **Days, streak, longest, reps:** Days is every day you've practiced and only
  ever grows. Streak is consecutive days, and stays alive through today if you
  practiced yesterday. A missed day resets the streak but never Days or Reps.
- **Grace window:** until noon, anything can be logged for yesterday, for late
  nights. Streaks are recalculated from the days you actually logged, so a
  late entry fills its gap.
- **Reps:** every entry adds to its skill, its declaration, and its ritual.
  Each count fills a ring toward the next milestone (10, 20, 50, 100, then every
  100), celebrates, and starts the next ring fresh. Days and streaks celebrate
  at the same milestones.
- **History:** a calendar of practice days (tap a day for a read-only summary)
  and a list of past weekly reflections.
- **Weekly reflection:** seven questions, the last one optional.

## Editing content

Everything people see lives in `content/prompts.php`. Once a key is in use,
keep it; change the text freely.

## Files

| File | What it does |
|---|---|
| `index.php` | Login gate and page shell (header, Today and History tabs) |
| `api.php` | JSON endpoints: `state`, `entry`, `declaration`, `declaration_archive`, `weekly`, `history_month`, `history_day`, `history_weekly` |
| `bootstrap.php` | Loads config, WordPress, and the libraries |
| `lib/db.php` | Tables, migrations, user profiles |
| `lib/streaks.php` | Days, streaks, and the grace window (user's timezone) |
| `lib/entries.php` | Daily entries, reps, and milestone rings |
| `lib/history.php` | Month calendar, day summaries, weekly reflections |
| `lib/declarations.php` | Declarations (up to 3, with version history) |
| `lib/content.php` | Weekly questions and the page's state |
| `content/prompts.php` | Moods, skills (conscious rest first), clearing, gratitude, weekly questions |
| `assets/app.js` | Front end, including voice-to-text |
| `assets/app.css` | Kaleidoscope theme (light and dark mode) |
| `assets/fonts/` | Jost, hosted with the app (SIL Open Font License) |

## Voice-to-text

Uses the browser's built-in Web Speech API. Works in Chrome, Edge, and
Safari; the mic button is hidden in Firefox. Audio is processed by the
browser vendor (Google or Apple), not this server.

## Roadmap

- Settings: choose your timezone
- Export your history (for reviewing patterns, or with AI)
- Friends: see each other's streaks, send nudges
- Installable app (PWA) with daily push reminders
- v2: community share space
