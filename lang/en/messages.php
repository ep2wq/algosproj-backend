<?php

// Messages the public API returns. Arabic: lang/ar/messages.php. Laravel's own validation
// messages stay the framework's English; lang/ar/validation.php has the Arabic ones we use.
return [
    'invalid_credentials' => 'Wrong email or password.',
    'banned' => 'This account is banned.',
    'logged_out' => 'Logged out',
    'code_too_large' => 'The code may not be larger than 64 KB.',
    'runner_unavailable' => 'Code runner unavailable, try again shortly',
    'unauthenticated' => 'Unauthenticated.',

    // Framework errors, only replaced in Arabic (bootstrap/app.php); English keeps Laravel's text.
    'not_found' => 'Not found.',
    'too_many_attempts' => 'Too Many Attempts.',
];
