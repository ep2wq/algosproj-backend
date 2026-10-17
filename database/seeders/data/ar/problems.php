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
    'even-or-odd' => [
        'title' => 'زوجي أم فردي',
        'description' => <<<'TEXT'
اطبع `even` إذا كان العدد الصحيح `n` زوجيًا و`odd` إذا كان فرديًا. الصفر زوجي، والأعداد السالبة يمكن أن تكون زوجية أو فردية أيضًا: `-3` فردي و`-4` زوجي.

~Input~
سطر واحد فيه العدد الصحيح `n`.

~Output~
سطر واحد: `even` أو `odd`، بأحرف صغيرة.

~Constraints~
`-1000000000 <= n <= 1000000000`
TEXT,
    ],
    'fizzbuzz' => [
        'title' => 'FizzBuzz',
        'description' => <<<'TEXT'
لكل عدد من `1` إلى `n`، اطبع سطرًا واحدًا: `FizzBuzz` إذا كان العدد من مضاعفات `3` و`5` معًا، و`Fizz` إذا كان من مضاعفات `3`، و`Buzz` إذا كان من مضاعفات `5`، وإلا فالعدد نفسه.

~Input~
سطر واحد فيه العدد الصحيح `n`.

~Output~
`n` سطرًا، سطر لكل عدد من `1` إلى `n`، بالترتيب.

~Constraints~
`1 <= n <= 100`
TEXT,
    ],
    'count-vowels' => [
        'title' => 'عدّ حروف العلة',
        'description' => <<<'TEXT'
اقرأ سطرًا من النص واطبع عدد حروف العلة فيه. حروف العلة هي `a` و`e` و`i` و`o` و`u`، بأحرف صغيرة أو كبيرة.

~Input~
سطر واحد فيه النص `s`. قد يكون فارغًا.

~Output~
سطر واحد فيه عدد حروف العلة.

~Constraints~
طول `s` بين `0` و`1000`، شاملًا الطرفين.
لا يحتوي `s` إلا على أحرف ومسافات. يمكن أن تظهر المسافات في أي مكان، بما في ذلك البداية أو النهاية، وعدة مسافات متتالية، ويمكن أن يتكون `s` من مسافات فقط.
TEXT,
    ],
    'factorial' => [
        'title' => 'Factorial',
        'description' => <<<'TEXT'
اطبع الـ~factorial~ للعدد `n`، ويُكتب `n!`: حاصل الضرب `1 * 2 * 3 * ... * n`. وبحسب التعريف `0!` يساوي `1`.

~Input~
سطر واحد فيه العدد الصحيح `n`.

~Output~
سطر واحد فيه `n!`.

~Constraints~
`0 <= n <= 20`
أكبر إجابة، `20! = 2432902008176640000`، تتسع في ~signed 64-bit integer~ لكنها لا تتسع في ~32-bit integer~.
TEXT,
    ],
];
