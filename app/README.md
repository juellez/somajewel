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

- **Tabs:** Today, Progress, Reflect, and History along the bottom. At the
  top, the compass opens Declarations and the cog shows who's signed in, with
  Log Out.
- **Today:** the counters (Days, Streak, Longest, Reps), a checklist of today's
  rituals with the practices named ("Centering ×3"), then the rituals
  themselves: morning clearing, practice (declaration + skill + mood check),
  and evening gratitude. Any one of them counts for the day. The practice card
  opens on arrival and stays open after logging, ready for the next rep. Each
  skill shows a teal badge with today's reps and its running total. Conscious
  Rest is a skill, so choosing rest with intention counts too.
- **Moods:** twelve, in a 4×3 grid, from lifted to settled to heavy. "Other"
  pairs with the optional note.
- **Progress:** the counters, a ring per ritual, each declaration with the
  practices done under it (counted for that declaration only), retired
  declarations with Reactivate, then a ring per skill. Notes live in History
  (linked at the bottom).
- **Reflect:** this week's reflection, one row per question with a check once
  answered. On the days set in content (Friday–Sunday by default), Today shows
  a Weekly Reflection line until something is answered.
- **Declarations:** up to 3 active. A short "What's a declaration?" card, then
  each declaration with its reps and a bar toward 300 (muscle memory), then
  3,000 (embodiment); both celebrate. Refine keeps the earlier wording;
  Retire takes it off the practice card and keeps its reps. New ones can also
  be added from the practice card (+ New).
- **Days, streak, longest, reps:** Days is every day you've practiced and only
  ever grows. Streak is consecutive days, and stays alive through today if you
  practiced yesterday. A missed day resets the streak but never Days or Reps.
- **Grace window:** until noon, anything can be logged for yesterday, for late
  nights. Streaks are recalculated from the days you actually logged, so a
  late entry fills its gap. Clearing and gratitude are once a day: Yesterday
  only appears on their cards if yesterday doesn't have one yet. Practices can
  always be logged for yesterday during the window.
- **Reps:** every entry adds to its ritual, its skill, and its declaration.
  Ritual and skill counts each fill a ring toward their next milestone,
  celebrate, and start the next ring fresh. Days and streaks celebrate at the
  same milestones. Conscious Rest is counted (and counts toward the Practice
  ring) but has no milestones of its own. Declarations have their own goals
  (300, then 3,000).
- **Milestones:** 10, 20, 50, 100, 150, 300, 500, 750, 1,000, then every
  1,000. Set in `lib/entries.php` (`USP_MILESTONES`); a skill opts out with
  `'milestones' => false` in `content/prompts.php`.
- **History:** a calendar of practice days and a list of past weekly
  reflections. Tap a day for a read-only summary: Rituals (clearing, then
  gratitude), then Practices grouped by declaration, one line per rep in the
  order logged, each with its mood and note.
- **Settings** (cog → Settings): time zone (set from the device on first
  visit; Today offers to switch if the device later shows a different zone),
  week start (Sunday or Monday), and beta switches for the app name and icon
  style.
- **Weekly reflection timing:** each week's reflection opens Friday and stays
  editable until the next one opens; Today nudges Friday through Monday. Then
  it's read-only in History.
- **Practices** (practice card → Edit practices): show or hide practices on
  your card, rename one (your name shows everywhere), and add your own with how
  you'll practice it. Hidden and deleted practices keep their history. Names of
  added practices appear in WordPress admin (Practice stats → Feedback).
- **Welcome page** (`/app/welcome`, public; served by `welcome.php` via `.htaccess`): what the app is and how to join,
  to share with friends and clients. "Join the beta" goes to WordPress
  registration while it's on (Settings → General → Membership); otherwise the
  page says it's invitation-only. Testers share it from Settings → Invite a
  friend, or the footer.
- **Community** (people icon, top bar): opt-in highlights. Two separate
  switches, both off by default: share progress (Days, Streak, Longest, Reps,
  and when you last practiced) and share declarations (active ones). Nothing
  shares until you save a display name (clearing it stops sharing). Either
  switch then shows you on the board under that name and lets you see everyone
  else who shares. Never
  shared: notes, moods, answers, or which practices. The switches live on the
  Community screen; Settings links there. WordPress admin shows who shares.
- **Share Feedback** (Settings, or the footer): something that's off, an idea, or a vote in the
  name poll. Saved with the screen, device, and version; read it in WordPress
  admin under Practice stats → Feedback.
- **Version:** the footer shows `v0.3.0 · a1b2c3d` (USP_VERSION in
  `bootstrap.php`, plus the commit the deploy stamps into `build.json`).
- **Icon style test (beta):** each person sees style A (lotus for Progress,
  sparkle for Sacred) or B (footprints for Progress, lotus for Sacred), and
  can switch in the settings menu.

## Editing content

Everything people read lives in **`content/prompts.php`**; no code needed.
Change any text freely. Keep the `'key'` values (they're stored with
entries), and keep words in `{braces}`, which the app fills in.

| To change… | Look in `content/prompts.php` under… |
|---|---|
| Moods (labels, icons, order) | `'moods'` |
| Practices (names, descriptions, note prompts) | `'skills'` |
| Morning clearing questions | `'clearing'` |
| Evening gratitude steps | `'gratitude'` |
| Weekly reflection questions | `'weekly'` |
| Tab names, top bar, settings menu, footer links | `'ui'` → `'nav'` |
| Greetings | `'ui'` → `'greeting'` |
| Counter labels and the line under them | `'ui'` → `'counters'` |
| Today's checklist, and which days the weekly line shows | `'ui'` → `'checklist'` |
| Card labels and buttons (clearing, practice, gratitude) | `'ui'` → `'clearing'`, `'practice'`, `'gratitude'` |
| The Today / Yesterday choice | `'ui'` → `'when'` |
| Reflect tab | `'ui'` → `'reflect'` |
| Declarations screen, including "What's a declaration?" | `'ui'` → `'declarations'` |
| Progress tab | `'ui'` → `'progress'` |
| History tab | `'ui'` → `'history'` |
| Messages after saving | `'ui'` → `'toasts'` |
| Milestone celebrations | `'ui'` → `'celebrations'` |
| Error messages | `'ui'` → `'errors'`, `'mic'` |
| App name, and the names to try (beta) | `'ui'` → `'app'` → `'name'`, and `'name_test'` |
| Icon style test (A/B) | `'ui'` → `'icon_test'` |
| Settings screen and the time zone prompt | `'ui'` → `'settings'`, `'timezone_prompt'` |
| When the weekly reflection opens | `'ui'` → `'reflect'` → `'opens_on'` |
| Practices screen, including "What makes it a practice?" | `'ui'` → `'practices'` |
| Community screen and sharing switches | `'ui'` → `'community'` |
| Welcome page and Invite a friend | `'ui'` → `'welcome'` |
| Share Feedback (the poll uses the names in `'name_test'`) | `'ui'` → `'feedback'` |

Icons are named from `assets/icons.svg` (Phosphor). To use one that isn't
there yet, add it to that file (see the note at its top).

Numbers that shape the practice live in code: milestones (`USP_MILESTONES`)
and declaration goals (`USP_DECLARATION_GOALS`) in `lib/entries.php`, the
limit of 3 declarations in `lib/declarations.php`, and the noon grace window
in `lib/streaks.php`.

## Files

| File | What it does |
|---|---|
| `index.php` | Login gate and page shell (header, compass, settings menu, tab bar) |
| `welcome.php` | Public welcome page to share, with a link to join |
| `api.php` | JSON endpoints: `state`, `entry`, `declaration`, `declaration_retire`, `declaration_reactivate`, `weekly`, `history_month`, `history_day`, `history_weekly` |
| `bootstrap.php` | Loads config, WordPress, and the libraries |
| `lib/db.php` | Tables, migrations, user profiles |
| `lib/streaks.php` | Days, streaks, and the grace window (user's timezone) |
| `lib/entries.php` | Daily entries, reps, and milestone rings |
| `lib/history.php` | Month calendar, day summaries, weekly reflections |
| `lib/community.php` | Community: what each person shares, and the board of people who share |
| `lib/practices.php` | Each person's practice list: show/hide, renames, their own practices |
| `lib/declarations.php` | Declarations (up to 3 active, with version history; retire and reactivate) |
| `lib/content.php` | Weekly questions and the page's state |
| `content/prompts.php` | All content: moods, skills, clearing, gratitude, weekly questions, and every screen's text (`'ui'`) |
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
- Friends: see each other's streaks, send nudges
- Installable app (PWA) with daily push reminders
- v2: community share space

More ideas and plans are tracked as [GitHub issues](https://github.com/juellez/somajewel/issues).
