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
        'title'     => 'Morning Clearing',
        'intro'     => 'Clear what\'s in the way, then take a stand for your way of being today.',
        'questions' => [
            ['key' => 'worries',        'text' => 'Fears, concerns, complaints, worries?'],
            ['key' => 'story',          'text' => 'What am I making this mean?'],
            ['key' => 'interpretation', 'text' => 'Interpretation or fact? Is it empowering or disempowering?'],
            ['key' => 'stand',          'text' => 'What way of being (not "fixing") do I want to be today? My stand.'],
        ],
    ],

    'gratitude' => [
        'title' => 'Generative Gratitude',
        'intro' => 'We can practice generating gratitude through extension, creating space to explore what naturally wells up (or doesn\'t).',
        'steps' => [
            ['title' => 'Ground', 'text' => 'Extend down into the ground. Feel for gratitude.'],
            ['title' => 'Sky',    'text' => 'Extend up to the sky. Feel for gratitude.'],
            ['title' => 'Out',    'text' => 'Extend out to others and the world. Feel for gratitude.'],
            ['title' => 'Self',   'text' => 'Extend to yourself, like a hug. Feel for gratitude.'],
        ],
        'note_placeholder' => 'Optional: what welled up, or anything else from today',
    ],


    // One set = the same questions every week. Add more sets to rotate.
    'weekly' => [
        [
            'key'       => 'reflection',
            'title'     => 'Weekly Reflection',
            'intro'     => '"Follow effective action with quiet reflection. From the quiet reflection will come even more effective action." — Peter F. Drucker',
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

];
