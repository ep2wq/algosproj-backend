<?php

// Arabic problem text (problems.title_ar, description_ar), keyed by problem slug. English is in
// ../lessons.php. Same meaning, numbers and output format as the English.
// Arabic keeps technical terms in English: ~term~ in prose, `inline code` for code and values.
// Rules and terms: GLOSSARY.md in this folder and the README ("Content languages").

return [
    'warm-up' => [
        'title' => 'ضاعف العدد',
        'description' => <<<'TEXT'
اقرأ عددًا صحيحًا `n` واطبع `2 * n`.

~Input~
سطر واحد فيه العدد الصحيح `n`.

~Output~
سطر واحد فيه `2 * n`.

~Constraints~
`-1000000000 <= n <= 1000000000` (الإجابة تتسع في ~32-bit signed integer~).
TEXT,
    ],
    'sum-of-an-array' => [
        'title' => 'مجموع عناصر الـ array',
        'description' => <<<'TEXT'
اطبع مجموع الأعداد الصحيحة وعددها `n`.

~Input~
السطر 1: `n`، عدد العناصر.
السطر 2: الأعداد الصحيحة وعددها `n`، تفصل بينها مسافة واحدة (سطر فارغ عندما `n = 0`).

~Output~
سطر واحد فيه المجموع.

~Constraints~
`0 <= n <= 1000`
كل عدد صحيح بين `-1000000` و`1000000`، شاملًا الطرفين.
TEXT,
    ],
    'maximum-of-an-array' => [
        'title' => 'أكبر عنصر في الـ array',
        'description' => <<<'TEXT'
اطبع أكبر الأعداد الصحيحة وعددها `n`.

~Input~
السطر 1: `n`، عدد العناصر.
السطر 2: الأعداد الصحيحة وعددها `n`، تفصل بينها مسافة واحدة.

~Output~
سطر واحد فيه أكبر عدد صحيح.

~Constraints~
`1 <= n <= 1000`
كل عدد صحيح بين `-1000000000` و`1000000000`، شاملًا الطرفين.
TEXT,
    ],
];
