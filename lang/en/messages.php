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
    'email_unverified' => 'Verify your email to submit code.',
    'verification_sent' => 'We are sending a new verification link to your email.',
    'already_verified' => 'Your email is already verified.',
    'reset_link_sent' => 'If an account exists for that email, we have sent a link to reset the password.',
    'password_reset' => 'Your password has been reset. You can now log in.',
    'reset_invalid' => 'This reset link is invalid or has expired. Request a new one.',
    'social_expired' => 'Your sign-in expired. Please start again.',

    // Framework errors, only replaced in Arabic (bootstrap/app.php); English keeps Laravel's text.
    'not_found' => 'Not found.',
    'too_many_attempts' => 'Too Many Attempts.',
];
