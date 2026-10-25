# Unstruck Practice

A small self-coaching and daily somatic practice app. Weekly reflection
questions, daily check-ins with streaks, and voice-to-text answers.

It runs as a plain PHP app beside a WordPress site and uses WordPress only
for logins. Anyone with a WordPress account can use it.

## Requirements

- PHP 8.1+ (built and tested against 8.3)
- A WordPress site on the same domain (for logins and the database connection)
- HTTPS (browsers only allow the microphone on secure pages)

## Setup

1. Put this folder inside the WordPress root, so it's reachable at `/app`.
2. Optional: copy `config.sample.php` to `config.php` to change defaults.
3. Visit `/app`. You'll be sent to the WordPress login, then back.

Database tables (`{prefix}usp_*`) are created automatically on first visit.

## How it works

- **Daily rituals:** morning clearing, practice (declaration + skill + after-mood),
  evening gratitude, and conscious rest. Any one of them keeps the daily streak.
- **Reps:** every entry adds to its skill, its declaration, and its ritual.
  Each count fills a ring toward the next milestone (10, 20, 50, 100, then every
  100), celebrates, and starts the next ring fresh.
- **Streak freezes:** one is earned every 7 days of streak (max 2) and covers a
  missed day automatically.
- **Weekly reflection:** seven questions, the last one optional.

## Editing content

Everything people see lives in `content/prompts.php`. Once a key is in use,
keep it; change the text freely.

## Files

| File | What it does |
|---|---|
| `index.php` | Login gate and page shell |
| `api.php` | JSON endpoints: `state`, `entry`, `declaration`, `declaration_archive`, `weekly` |
| `bootstrap.php` | Loads config, WordPress, and the libraries |
| `lib/db.php` | Tables, migrations, user profiles |
| `lib/streaks.php` | Streak and streak-freeze logic (user's timezone) |
| `lib/entries.php` | Daily entries, reps, and milestone rings |
| `lib/declarations.php` | Declarations (up to 3, with version history) |
| `lib/content.php` | Weekly questions and the page's state |
| `content/prompts.php` | Moods, skills, clearing, gratitude, rest, weekly questions |
| `assets/app.js` | Front end, including voice-to-text |
| `assets/app.css` | Styles (light and dark mode) |

## Voice-to-text

Uses the browser's built-in Web Speech API. Works in Chrome, Edge, and
Safari; the mic button is hidden in Firefox. Audio is processed by the
browser vendor (Google or Apple), not this server.

## Roadmap

- Settings: choose your timezone
- History view and export (for reviewing patterns, or with AI)
- Friends: see each other's streaks, send nudges
- Installable app (PWA) with daily push reminders
- v2: community share space
