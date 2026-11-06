<?php

// Bug reports (README "Bug reports").
return [
    // bugreports:prune deletes fixed and wont_fix reports this many days after their status
    // changed, and any report this many days after it was created.
    'closed_retention_days' => 30,
    'retention_days' => 180,
];
