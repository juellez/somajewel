<?php
/**
 * App content: edit freely.
 *
 * Keys are stored with saved entries and answers. Once a key is in use,
 * don't change it (edit the text freely). To add something, give it a new key.
 */

return [

    // How do you feel now? Shown as a 4×3 grid, in this order: lifted,
    // settled, heavy. 'icon' is a Phosphor icon in assets/icons.svg.
    'moods' => [
        ['key' => 'elated',      'icon' => 'lightning',      'label' => 'Energized'],
        ['key' => 'joyful',      'icon' => 'smiley',         'label' => 'Joyful'],
        ['key' => 'loving',      'icon' => 'heart',          'label' => 'Loving'],
        ['key' => 'sacred',      'icon' => 'flower-lotus',   'label' => 'Sacred'],
        ['key' => 'calm',        'icon' => 'feather',        'label' => 'Peaceful'],
        ['key' => 'grounded',    'icon' => 'mountains',      'label' => 'Grounded'],
        ['key' => 'tender',      'icon' => 'plant',          'label' => 'Tender'],
        ['key' => 'blah',        'icon' => 'smiley-meh',     'label' => 'Blah'],
        ['key' => 'anxious',     'icon' => 'smiley-nervous', 'label' => 'Anxious'],
        ['key' => 'sad',         'icon' => 'smiley-sad',     'label' => 'Sad'],
        ['key' => 'heartbroken', 'icon' => 'heart-break',    'label' => 'Heartbroken'],
        ['key' => 'other',       'icon' => 'dots-three',     'label' => 'Other'],
    ],

    // Practice menu. What makes it somatic: intention, awareness, repetition.
    // Optional per skill: 'note_placeholder' (the note box's prompt) and
    // 'milestones' => false (count its reps, but no ring or celebrations).
    'skills' => [
        ['key' => 'rest',           'title' => 'Conscious Rest', 'description' => 'Choosing rest with intention is a practice, too.',
         'note_placeholder' => 'Optional: what are you resting from, or for?', 'milestones' => false],
        ['key' => 'one-breath',     'title' => 'One Breath',     'description' => 'Take one breath, right now, with your declaration.'],
        ['key' => 'centering',      'title' => 'Centering',      'description' => 'Length, width, depth. Note how your body wants to organize around your sense of purpose.'],
        ['key' => 'mindful-minute', 'title' => 'Mindful Minute', 'description' => 'One minute of full attention.'],
        ['key' => 'breath',         'title' => 'Breathwork',         'description' => 'Coherence Breath. Any breathwork.'],
        ['key' => 'meditation',     'title' => 'Meditation',     'description' => 'Any style, any length.'],
        ['key' => 'jo',             'title' => 'Jo',             'description' => 'Kata, partner practice, or any jo work.'],
        ['key' => 'mutual-connection',             'title' => 'Hand on Heart',             'description' => 'Mutual Connection with Self, Spirit, or another person.'],
        ['key' => 'relational',             'title' => 'Relational',             'description' => 'Centered request or yes/no/maybe. Any practice for the sake of building your capacity to be in Right Relation with self and others.'],
        ['key' => 'prayer',             'title' => 'Prayer',             'description' => 'Daily prayer, devotions, intentions.'],
    ],

    'clearing' => [
        'title'     => 'Clearing & Intention',
        'intro'     => 'Honor what is. Clear what you\'re ready to release. Set your intentions.',
        'questions' => [
            ['key' => 'worries',        'text' => 'Any fears, concerns, complaints, worries?'],
            ['key' => 'story',          'text' => 'What am I making this mean?'],
            ['key' => 'interpretation', 'text' => 'Interpretation or fact? Is it empowering or disempowering?'],
            ['key' => 'stand',          'text' => 'What way of being (not "fixing") do I want to be today? My "stand".'],
        ],
    ],

    'gratitude' => [
        'title' => 'Generative Gratitude',
        'intro' => 'We can practice gratitude through physical extension, creating space to explore what naturally wells up (or doesn\'t).',
        'steps' => [
            ['title' => 'Ground', 'text' => 'Extend down into the ground.'],
            ['title' => 'Sky',    'text' => 'Extend up to the sky.'],
            ['title' => 'Out',    'text' => 'Extend out to others and the world.'],
            ['title' => 'Self',   'text' => 'Extend to yourself, like a hug.'],
        ],
        'note_placeholder' => 'Optional: what welled up, or anything else from today',
    ],


    // One set = the same questions every week. Add more sets to rotate.
    'weekly' => [
        [
            'key'       => 'reflection',
            'title'     => 'Weekly Reflection',
            'intro'     => '',
            'questions' => [
                ['key' => 'wins',      'text' => 'Wins',             'hint' => 'What am I proud of myself for, or celebrating?'],
                ['key' => 'truth',     'text' => 'Truth',            'hint' => 'Something I know is true but don\'t want to admit.'],
                ['key' => 'insight',   'text' => 'Insight',          'hint' => 'What did I notice or learn? Where did I feel resistance, or excitement?'],
                ['key' => 'release',   'text' => 'Release',          'hint' => 'What am I ready to let go of?'],
                ['key' => 'gratitude', 'text' => 'Gratitude',        'hint' => 'Take a moment to center. What bubbles up?'],
                ['key' => 'intention', 'text' => 'Intention',        'hint' => 'What am I holding for next week? One word is enough.'],
                ['key' => 'coaching',  'text' => 'Coaching request', 'hint' => 'What would I like to be coached on? Skip on low-energy weeks.', 'optional' => true],
            ],
        ],
    ],

    // ------------------------------------------------------------------
    // Screen text: every label, heading, button, and message in the app.
    // Edit freely. Words in {braces} are filled in by the app; keep them.
    // ------------------------------------------------------------------
    'ui' => [

        'app' => [
            'name'       => 'SomaRosa',
            'loading'    => 'Loading your practice…',
            'load_error' => 'Something went wrong loading your practice. Please refresh.',
            'back'       => 'Back',
            'saving'     => 'Saving…',
            'save_error' => 'Not saved. Please try again.',
            'optional'   => 'optional',
        ],

        // Tabs, the top bar, the settings menu, and the footer.
        'nav' => [
            'today'        => 'Today',
            'progress'     => 'Progress',
            'reflect'      => 'Reflect',
            'history'      => 'History',
            'declarations' => 'Declarations',
            'settings'     => 'Settings',
            'signed_in_as' => 'Signed in as',
            'log_out'      => 'Log Out',
            'terms'        => 'Terms of Use & Privacy',
            'feedback'     => 'Roadmap & Feature Requests',
        ],

        // Beta: two icon styles to compare. Each person gets one; the
        // settings menu lets them switch. To end the test, put the winner
        // in 'a' and delete 'b' (the switch disappears).
        // Beta: names to try on. Each person can switch in Settings; the
        // header and page title follow. Delete 'name_test' to end it.
        'name_test' => [
            'label'   => 'App name',
            'options' => ['SomaJewel', 'Melete', 'Soma Rosa'],
        ],

        'icon_test' => [
            'label' => 'Icon style',
            'a'     => ['progress' => 'flower-lotus', 'sacred' => 'sparkle'],
            'b'     => ['progress' => 'footprints',   'sacred' => 'flower-lotus'],
        ],

        'greeting' => [
            'morning'   => 'Good morning, {name}',
            'afternoon' => 'Good afternoon, {name}',
            'evening'   => 'Good evening, {name}',
        ],

        // The plum counter card (Today and Progress).
        'counters' => [
            'days'          => 'Days',
            'streak'        => 'Streak',
            'longest'       => 'Longest',
            'reps'          => 'Reps',
            'note_done'     => 'You showed up today.',
            'note_grace'    => 'Missed yesterday? You can still log it until {until}.',
            'note_streak'   => 'Any practice today keeps your streak. Even one breath.',
            'note_days'     => '{days} days of practice, and counting. Every streak starts with one breath.',
            'note_one_day'  => '1 day of practice, and counting. Every streak starts with one breath.',
            'note_start'    => 'Every streak starts with one breath.',
        ],

        // Today's checklist.
        'checklist' => [
            'clearing'        => 'Clearing',
            'practice'        => 'Practice',
            'gratitude'       => 'Gratitude',
            'weekly'          => 'Weekly Reflection',
            'weekly_progress' => '{n} of {total}',
            // Days the Weekly Reflection line shows on Today, until something
            // is answered. 0 = Sunday … 6 = Saturday.
            'weekly_days'     => [5, 6, 0, 1],
        ],

        'cards' => [
            'done'  => 'Done',
            'today' => 'today',
        ],

        'clearing' => [
            'eyebrow' => 'Morning',
            'save'    => 'Save clearing',
        ],

        'practice' => [
            'eyebrow'           => 'Practice',
            'title'             => 'Log a practice',
            'first_declaration' => 'Start with a declaration, in your own words. Every practice builds toward it, and you can refine it anytime.',
            'declaration'       => 'Declaration',
            'new_declaration'   => 'New',
            'skill'             => 'What are you practicing?',
            'mood'              => 'Mood check: What do you notice in your body now and how would you describe that in one word (or a few)?',
            'note'              => 'Optional note',
            'save'              => 'Log practice',
        ],

        'gratitude' => [
            'eyebrow' => 'Evening',
            'mood'    => 'Mood check: What do you notice in your body after your practice?',
            'save'    => 'I practiced gratitude',
        ],

        // "Log this for Today | Yesterday", shown until noon.
        'when' => [
            'label'     => 'Log this for',
            'today'     => 'Today',
            'yesterday' => 'Yesterday',
            'hint'      => 'Late night or forgot to track? Until {until}, you can still log practice for yesterday.',
        ],

        'reflect' => [
            'title'       => 'Reflect',
            'lede'        => '"Follow effective action with quiet reflection. From the quiet reflection will come even more effective action." — Peter F. Drucker',
            'this_week'   => 'This week · {date}',
            'last_week'   => 'Last week · {date}',
            'open_until'  => 'Last week\'s reflection is available for editing until {day}, when the next reflection opens.',
            // The day each week's reflection opens (0 = Sunday … 6 = Saturday).
            // It stays open, and editable, until the next one opens.
            'opens_on'    => 5,
            'answered'    => 'of {total}',
            'save'        => 'Save',
            'saved'       => 'Saved',
            'unsaved'     => 'Unsaved',
            'save_failed' => 'Not saved. Try again.',
            'past'        => 'Past reflections are in History',
        ],

        'declarations' => [
            'title'          => 'Declarations',
            'intro_title'    => 'What\'s a declaration?',
            'intro'          => 'A declaration is a commitment to a future you bring into being: your North Star. You don\'t *have* a declaration, you *are* a declaration. Most find this challenging at the beginning, because we\'re And you embody it through practice.',
            'intro_points'   => [
                'Say it as "I am a commitment to…" or "I am a stand for…"',
                'Every practice connects to it: intention, noticing, repetition.',
                '300 reps builds muscle memory; 3,000: embodiment.',
                'It\'ll feel awkward when you start, more natural as you build muscle memory, and automatic as you embody it.',
                'It evolves. You can refine the words as you grow.',
            ],
            'eyebrow'        => 'Your North Star',
            'count'          => '{n} of {max}',
            'reps'           => '{n} reps',
            'one_rep'        => '1 rep',
            'embodied'       => 'Embodied',
            'empty'          => 'No declaration yet. Write your first one below.',
            'add'            => 'Add a declaration',
            'placeholder'    => 'I am a stand for…  /  I am a commitment to…',
            'refine'         => 'Refine',
            'retire'         => 'Retire',
            'reactivate'     => 'Reactivate',
            'save'           => 'Save declaration',
            'save_refined'   => 'Save',
            'cancel'         => 'Cancel',
            'retire_confirm' => 'Retire this declaration? It leaves your practice card. Its reps and history stay on Progress, and you can reactivate it later.',
            'saved'          => 'Declaration saved.',
            'refined'        => 'Declaration refined.',
            'retired'        => 'Declaration retired. You\'ll find it on Progress.',
            'reactivated'    => 'Declaration reactivated.',
            'full'           => 'You\'re holding {max}. Retire one to make room.',
        ],

        // Settings screen (cog → Settings).
        'settings' => [
            'title'          => 'Settings',
            'account_title'  => 'Account',
            'week_title'     => 'Week',
            'week_body'      => 'Your week starts on this day, in History\'s calendar and for weekly reflections.',
            'week_start'     => 'Week starts on',
            'sunday'         => 'Sunday',
            'monday'         => 'Monday',
            'beta_title'     => 'Beta',
            'beta_body'      => 'Try these out and tell me what you think in Share Feedback.',
            'timezone_title' => 'Time zone',
            'timezone_body'  => 'Your practice day starts at midnight in this time zone, and the noon cutoff for logging yesterday follows it too.',
            'now'            => 'It\'s {time} there now.',
            'use_device'     => 'Use this device\'s time zone ({zone})',
            'save'           => 'Save',
            'saved'          => 'Settings saved.',
            'about_title'    => 'About',
            'version'        => 'Running {version}',
        ],

        // Shown on Today when this device's time zone differs from yours
        // (traveling, or a new phone).
        'timezone_prompt' => [
            'text'   => 'This device is on {device}. Your practice days follow {current}.',
            'switch' => 'Switch to {device}',
            'keep'   => 'Keep {current}',
        ],

        'progress' => [
            'rituals_eyebrow'      => 'Your Day',
            'time_clearing'        => 'Morning',
            'time_practice'        => 'All day',
            'time_gratitude'       => 'Evening',
            'rituals_title'        => 'Rituals',
            'skills_eyebrow'       => 'Your Skills',
            'skills_title'         => 'Practices',
            'skills_body'          => 'Every rep builds muscle memory.',
            'declarations_eyebrow' => 'By Declaration',
            'declarations_title'   => 'Your North Star',
            'toward'               => '{n} reps toward {goal}',
            'embodied'             => '{n} reps · embodied',
            'no_reps'              => 'No practices logged with this one yet.',
            'retired'              => 'Retired',
        ],

        'history' => [
            'daily'          => 'Daily practice',
            'weekly'         => 'Weekly reflections',
            'today'          => 'Today',
            'loading'        => 'Loading…',
            'no_entries'     => 'No entries. Rest counts too.',
            'one_rep'        => '1 rep',
            'reps'           => '{n} reps',
            'legend_more'    => '4+',
            'legend_some'    => '2–3',
            'legend_today'   => 'Today',
            'previous_month' => 'Previous month',
            'next_month'     => 'Next month',
            'this_week'      => 'This week',
            'last_week'      => 'Last week',
            'week_of'        => 'Week of {date}',
            'answers'        => '{n} answers',
            'one_answer'     => '1 answer',
            'not_answered'   => 'Not answered yet.',
            'go_reflect'     => 'Reflect now',
            'no_reflections' => 'Your weekly reflections will collect here.',
            'load_error'     => 'Couldn\'t load your history. Please refresh.',
            'day_error'      => 'Couldn\'t load this day.',
        ],

        // Little messages after saving.
        'toasts' => [
            'first_of_day'     => 'Day {days}. You showed up.',
            'rep'              => 'Rep logged. Nicely done.',
            'yesterday_first'  => 'Logged for yesterday. Streak: {streak}.',
            'yesterday_rep'    => 'Rep logged for yesterday.',
        ],

        // Milestone messages. {n} is the count; {name} the skill or ritual.
        'celebrations' => [
            'skill'          => '{n} {name} reps!',
            'ritual'         => '{n} {name}!',
            'days'           => '{n} days of practice!',
            'streak'         => '{n}-day streak!',
            'declaration'    => '{n} reps of your declaration: muscle memory!',
            'declaration_3k' => '{n} reps of your declaration: embodied!',
            // How each ritual is named in its milestone ("10 clearings!").
            'ritual_names'   => [
                'clearing'  => 'clearings',
                'practice'  => 'practices',
                'gratitude' => 'gratitude',
            ],
        ],

        'errors' => [
            'declaration_required' => 'Choose a declaration first.',
            'skill_required'       => 'Choose what you practiced.',
            'mood_required'        => 'Tap how you feel now.',
            'empty'                => 'Write something in at least one box.',
            'limit'                => 'You can hold up to {max} declarations. Retire one to add another.',
            'grace_closed'         => 'It\'s past noon, so this can only be logged for today.',
        ],

        'mic' => [
            'label'               => 'Speak your answer',
            'stop'                => 'Stop listening',
            'not-allowed'         => 'Microphone access is blocked. Allow it in your browser\'s settings for this site, or type instead.',
            'service-not-allowed' => 'Voice input is turned off for this browser. Check its settings, or type instead.',
            'audio-capture'       => 'No microphone was found. Check that one is connected, or type instead.',
            'network'             => 'Voice input needs an internet connection. Try again, or type instead.',
            'no-speech'           => 'Didn\'t catch anything. Tap the mic and try again.',
            'stalled'             => 'Voice input didn\'t start. Tap the mic to try again.',
            'ready'               => 'Microphone is ready. Tap the mic again to speak.',
        ],
    ],

];
