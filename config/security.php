<?php

return [
    // The on/off switch lives in Admin > Settings. This is only the value used until a Super Admin saves one.
    'questions_default' => (bool) env('SECURITY_QUESTIONS', true),
    'questions_required' => 3,
    'max_wrong' => 5,
    'lock_minutes' => 15,
    'code_minutes' => 10,
    'failed_logins_suspicious' => 3,

    'questions' => [
        'first_pet' => 'What was the name of your first pet?',
        'first_school' => 'What was the name of your first school?',
        'birth_city' => 'In what city were you born?',
        'mother_maiden' => 'What is your mother\'s maiden name?',
        'first_car' => 'What was the make of your first car?',
        'childhood_street' => 'What street did you live on as a child?',
        'best_friend' => 'What was the first name of your childhood best friend?',
        'first_job' => 'What was your first job?',
        'favorite_teacher' => 'What was the last name of your favorite teacher?',
        'first_concert' => 'What was the first concert you went to?',
        'grandmother_first' => 'What is your maternal grandmother\'s first name?',
        'honeymoon_or_trip' => 'Where did you go on your first airplane trip?',
        'first_phone' => 'What was your first phone number\'s last four digits?',
        'childhood_nickname' => 'What was your childhood nickname?',
        'favorite_book' => 'What was your favorite book as a child?',
    ],
];
