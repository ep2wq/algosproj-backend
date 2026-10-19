<?php

// Admin API messages. English only: the admin area always sends Accept-Language: en, and any
// other language falls back to these (app.fallback_locale).
return [
    'forbidden' => 'Forbidden.',
    'owner_only' => 'Only an owner can do this.',
    'not_yourself' => "You can't do this to your own account.",
    'owner_artisan' => 'Owners can only be changed with the artisan command.',
    'admin_needs_owner' => 'Only an owner can do this to an admin.',
    'already_banned' => 'This user is already banned.',
    'not_banned' => 'This user is not banned.',
    'module_has_lessons' => 'This module has lessons. Move or delete its lessons first.',
    'lesson_has_problems' => 'This lesson has problems. Move or delete its problems first.',
    'problem_solved' => 'Users have already solved this problem, so it cannot be deleted. Edit it instead.',
    'slug_format' => 'The slug may only contain lowercase letters, digits and hyphens.',
    'body_too_large' => 'The :name may not be larger than 65,535 bytes.',
    'test_field_too_large' => 'The :field may not be larger than 20,000 bytes.',
    'test_cases_max' => 'A problem can have at most :max test cases.',
    'test_cases_total' => 'All test inputs and expected outputs of a problem may total at most 25,000 bytes; this change would make :total.',
    'amount_zero' => 'The amount may not be zero.',
    'points_below_zero' => "Points can't go below 0. This user has :points.",
    'confirm_email' => "Type the user's exact email to confirm.",
];
