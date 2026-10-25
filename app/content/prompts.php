<?php
/**
 * App content: edit freely.
 *
 * Keys are stored with saved entries and answers. Once a key is in use,
 * don't change it (edit the text freely). To add something, give it a new key.
 */

return [

    // How do you feel now? Shown as a 3×3 keypad, in this order.
    'moods' => [
        ['key' => 'elated',     'emoji' => '🤩', 'label' => 'Elated'],
        ['key' => 'joyful',     'emoji' => '😄', 'label' => 'Joyful'],
        ['key' => 'loving',     'emoji' => '🥰', 'label' => 'Loving'],
        ['key' => 'calm',       'emoji' => '😌', 'label' => 'Calm'],
        ['key' => 'tender',     'emoji' => '🥹', 'label' => 'Tender'],
        ['key' => 'blah',       'emoji' => '😐', 'label' => 'Blah'],
        ['key' => 'anxious',    'emoji' => '😟', 'label' => 'Anxious'],
        ['key' => 'sad',        'emoji' => '😢', 'label' => 'Sad'],
        ['key' => 'heartbroken','emoji' => '💔', 'label' => 'Heartbroken'],
    ],

    // Practice menu. What makes it somatic: intention, awareness, repetition.
    'skills' => [
        ['key' => 'one-breath',     'title' => 'One breath',     'description' => 'Take one breath, right now, with your declaration.'],
        ['key' => 'centering',      'title' => 'Centering',      'description' => 'Length, width, depth. Center around your declaration.'],
        ['key' => 'mindful-minute', 'title' => 'Mindful minute', 'description' => 'One minute of full attention.'],
        ['key' => 'breath',         'title' => 'Breath',         'description' => 'Any breathwork.'],
        ['key' => 'meditation',     'title' => 'Meditation',     'description' => 'Any length.'],
        ['key' => 'jo',             'title' => 'Jo',             'description' => 'Kata, partner practice, or any jo work.'],
    ],

    'clearing' => [
        'title'     => 'Morning clearing',
        'intro'     => 'Clear what is in the way, then take a stand for how you want to be today.',
        'questions' => [
            ['key' => 'worries',        'text' => 'Fears, concerns, complaints, worries'],
            ['key' => 'story',          'text' => 'What\'s the story? What am I making this mean?'],
            ['key' => 'interpretation', 'text' => 'Interpretation or fact? Is it empowering or disempowering?'],
            ['key' => 'stand',          'text' => 'What way of being (not "fixing") do I want to be today? My stand.'],
        ],
    ],

    'gratitude' => [
        'title' => 'Evening gratitude',
        'intro' => 'Don\'t force it. Let gratitude arise through extension and feeling, and name whatever wells up, silently is fine.',
        'steps' => [
            ['title' => 'Ground', 'text' => 'Extend down into the ground. Feel for gratitude.'],
            ['title' => 'Sky',    'text' => 'Extend up to the sky. Feel for gratitude.'],
            ['title' => 'Out',    'text' => 'Extend out to others and the world. Feel for gratitude.'],
            ['title' => 'Self',   'text' => 'Extend to yourself, like a hug. Feel for gratitude.'],
        ],
        'note_placeholder' => 'Optional: what welled up, or anything else from today',
    ],

    'rest' => [
        'title' => 'Conscious rest',
        'intro' => 'Choosing rest with intention is a practice too. It keeps your streak.',
        'note_placeholder' => 'Optional: what are you resting from, or for?',
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
