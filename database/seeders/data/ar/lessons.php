<?php

// Arabic lesson text (lessons.title_ar, body_ar), keyed by lesson slug. English is in
// ../lessons.php and ../lesson_bodies.php. Same headings, lists and code blocks in the same order;
// code blocks byte-identical to the English.
// Arabic keeps technical terms in English: ~term~ in prose, `inline code` for code and values.
// Rules and terms: GLOSSARY.md in this folder and the README ("Content languages").

return [
    'warm-up' => [
        'title' => 'الإحماء',
        'body' => <<<'TEXT'
يتواصل برنامجك مع نظام التصحيح عبر اثنين من الـ~streams~. يكتب نظام التصحيح الـ~input~ الخاص بالاختبار في الـ~standard input~، ثم يشغّل برنامجك، ويقرأ ما يطبعه في الـ~standard output~. يُقارن هذا النص بالإجابة المتوقعة. يُتجاهل الـ~whitespace~ في البداية والنهاية، أما كل ما عدا ذلك فيجب أن يطابقها تمامًا.

## مثال
الـ~input~ هو السطر `21`. يقرأ برنامجك النص `"21"`، ويحوّله إلى العدد الصحيح `21`، ثم يضاعفه إلى `42` ويطبع `42`. يقارن نظام التصحيح `"42"` بالقيمة المتوقعة `"42"`، ويعدّ الاختبار ناجحًا.

## طريقة التفكير في الحل
لكل حل ثلاثة أجزاء: اقرأ، ثم احسب، ثم اطبع. يصل الـ~input~ على شكل نص، لذا حوّله إلى أعداد قبل أي عملية حسابية. اطبع الإجابة فقط. أي رسالة مثل `"Enter a number:"` تصبح جزءًا من الـ~output~ وتُفشل الاختبار.

كل مقتطف كود أدناه يقرأ سطرًا واحدًا من الأعداد ويطبع عدد الأعداد الزوجية فيه. احتفظ بجزأي القراءة والطباعة، واستبدل الجزء الأوسط.

## ~Python~
    nums = list(map(int, input().split()))
    print(sum(1 for x in nums if x % 2 == 0))

## ~Java~ (يجب أن يكون اسم الـ~class~ هو `Main`)
    import java.util.Scanner;

    public class Main {
        public static void main(String[] args) {
            Scanner in = new Scanner(System.in);
            String[] parts = in.nextLine().trim().split(" ");
            int evens = 0;
            for (String p : parts) {
                if (Integer.parseInt(p) % 2 == 0) evens++;
            }
            System.out.println(evens);
        }
    }

## ~JavaScript~
    const input = require('fs').readFileSync(0, 'utf8');
    const nums = input.trim().split(/\s+/).map(Number);
    console.log(nums.filter((x) => x % 2 === 0).length);

## ~C++~
    #include <iostream>
    using namespace std;

    int main() {
        int x, evens = 0;
        while (cin >> x) {
            if (x % 2 == 0) evens++;
        }
        cout << evens << endl;
    }

## الـ~complexity~
الـ~time complexity~: `O(1)` للمسألة أدناه، لأن هناك عددًا واحدًا وعملية ضرب واحدة. أما المقتطفات أعلاه فتقرأ سطرًا كاملًا من `n` عددًا، وهذا يستغرق `O(n)`.
الـ~space complexity~: `O(1)` للمسألة أدناه، لأن الحل يحتفظ بقيمة واحدة فقط. أما المقتطفات فتخزن السطر، `O(n)`، ما عدا مقتطف ~C++~ الذي يقرأ عددًا واحدًا في كل مرة.

## انتبه إلى
- الأعداد السالبة والصفر: ضعف `-7` هو `-14`.
- أكبر قيم الـ~input~: الناتج `2 * 1000000000 = 2000000000` ما زال يتسع في `int` بحجم ~32-bit~، وحده الأقصى `2147483647`.
- أوامر الطباعة المتبقية من الـ~debugging~. إنها جزء من الـ~output~ أيضًا.

جرّب دورة القراءة ثم الحساب ثم الطباعة على المسألة أدناه، باللغة التي اخترتها للحل.
TEXT,
    ],
    'sum-of-an-array' => [
        'title' => 'مجموع عناصر الـ array',
        'body' => <<<'TEXT'
الـ~array~ مجموعة من القيم المخزنة واحدة تلو الأخرى، ولكل قيمة موضع مرقّم يبدأ من `0`. لجمع هذه القيم، استخدم ~running total~، كما تجمع آلة الدفع في المتجر سعر كل سلعة لحظة مسحها.

## مثال
الـ~input~ هو `n = 5` والأعداد `10 -2 7 0 5`.
ابدأ من `total = 0`.
أضف `10`: `total = 10`.
أضف `-2`: `total = 8`.
أضف `7`: `total = 15`.
أضف `0`: `total = 15`.
أضف `5`: `total = 20`.
اطبع `20`.

## طريقة التفكير في الحل
اقرأ `n` أولًا، ثم اقرأ `n` عددًا بالضبط. ابدأ المجموع من `0` لا من العدد الأول، حتى يعمل الحل مع الـ~array~ الفارغ أيضًا. أضف كل عدد مرة واحدة. لا حاجة إلى الأعداد بعد إضافتها، لذا يمكنك إضافة كل عدد فور قراءته بدلًا من تخزين الـ~array~ كله. هذا النمط، ~variable~ واحد يتغير خلال ~single pass~، يتكرر في كثير من المسائل اللاحقة.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل عدد يُقرأ ويُضاف مرة واحدة.
الـ~space complexity~: `O(1)` إضافية عند إضافة كل عدد فور قراءته؛ و`O(n)` عند تخزين الأعداد كلها أولًا. كلا الحلين مقبول بهذا الحجم.

## انتبه إلى
- الحالة `n = 0`. يكون السطر الثاني فارغًا والإجابة `0`. في ~Java~، يُرجع `"".split(" ")` ~array~ فيه ~string~ فارغ واحد، وتحويله إلى عدد يرمي ~exception~. قراءة `n` عددًا بالضبط باستخدام ~loop~ تتجنب ذلك.
- الأعداد السالبة. إنها تُنقص المجموع. لا تحتاج إلى معالجة خاصة، لكن اختبر الحل ببعضها، مثل `-5 -5 3 3 -1 0` التي مجموعها `-5`.
- الحجم. أكبر مجموع ممكن هو `1000 * 1000000 = 1000000000`، وهو يتسع في `int` بحجم ~32-bit~. المسائل ذات الحدود الأكبر تحتاج إلى ~type~ بحجم ~64-bit~: `long` في ~Java~ و`long long` في ~C++~. الأعداد الصحيحة في ~Python~ لا يحدث فيها ~overflow~ أبدًا، وأعداد ~JavaScript~ دقيقة حتى نحو `9 * 10^15`.

اكتب حل الـ~running total~ للمسألة أدناه، وتحقق من الحالة `n = 0` قبل الإرسال.
TEXT,
    ],
    'maximum-of-an-array' => [
        'title' => 'أكبر عنصر في الـ array',
        'body' => <<<'TEXT'
لإيجاد أكبر عدد، يكفي تذكّر شيء واحد أثناء المرور على الأعداد: أكبر قيمة ظهرت حتى الآن. كل عدد جديد إما أن يتجاوزها أو لا.

## مثال
الـ~input~ هو `3 1 4 1 5 2`.
ابدأ من `best = 3`، وهو العدد الأول.
`1` ليس أكبر من `3`، لذا تبقى قيمة `best` هي `3`.
`4` أكبر من `3`، لذا تصبح قيمة `best` هي `4`.
`1` ليس أكبر من `4`، لذا تبقى قيمة `best` هي `4`.
`5` أكبر من `4`، لذا تصبح قيمة `best` هي `5`.
`2` ليس أكبر من `5`، لذا تبقى قيمة `best` هي `5`.
اطبع `5`.

## طريقة التفكير في الحل
اجعل العدد الأول قيمة `best` الابتدائية، ثم قارن به كل عدد آخر مرة واحدة. لا تستبدل `best` إلا عندما يكون العدد أكبر منها. عند تساوي القيم لا يهم أيها يُحتفظ به، لأن المطبوع هو القيمة لا موضعها. كما في المجموع، هذا ~single pass~، لذا يمكنك مقارنة كل عدد فور قراءته دون تخزين الـ~array~ أبدًا. إجراء ~sorting~ على الـ~array~ ثم أخذ آخر عنصر فيه يعطي الإجابة الصحيحة أيضًا، لكنه يؤدي عملًا أكثر بكثير مما يلزم.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل عدد يُقارن مرة واحدة.
الـ~space complexity~: `O(1)` إضافية، لأن الحل لا يحتفظ إلا بقيمة `best` (إضافة إلى الـ~input~ إذا خُزّن).

## انتبه إلى
- أن تكون كل الأعداد سالبة. في `-8 -3 -9 -3 -12` الإجابة هي `-3`. إذا بدأت `best` من `0` فسيُطبع `0`، وهو ليس في الـ~array~ أصلًا. ابدأ من العنصر الأول، أو من أصغر قيمة ممكنة: `Integer.MIN_VALUE` في ~Java~، و`INT_MIN` في ~C++~، و`-Infinity` في ~JavaScript~، و`float('-inf')` في ~Python~.
- عدد واحد فقط. عندما `n = 1` لا يوجد ما يُقارن، وذلك العدد هو الإجابة.
- المدى. تتراوح القيم من `-1000000000` إلى `1000000000`. كلا الطرفين يتسع في `int` بحجم ~32-bit~، لذا يكفي `int` في ~Java~ و~C++~.
- تكرار القيمة العظمى. يظهر `-3` مرتين في `-8 -3 -9 -3 -12` أعلاه؛ ومع ذلك تبقى الإجابة `-3` فقط، وتُطبع مرة واحدة.

حُلّ المسألة أدناه في ~single pass~، دون ~sorting~.
TEXT,
    ],
    'even-or-odd' => [
        'title' => 'زوجي أم فردي',
        'body' => <<<'TEXT'
يصل الـ~input~ على شكل نص، لذا حوّله أولًا إلى عدد. في ~Python~ يكون ذلك `int(input())`. في ~Java~، استخدم `Scanner` و`nextInt()`. في ~C++~، `cin >> n`. في ~JavaScript~، اقرأ السطر واستدعِ `Number` عليه.

يكون العدد زوجيًا عندما ينقسم إلى نصفين صحيحين متساويين. الـ~remainder operator~، `%`، يعطي ما يتبقى بعد القسمة: `7 % 2` يساوي `1`، لأن `7 = 3 * 2 + 1`. لذا يكون `n` زوجيًا إذا وفقط إذا كان `n % 2` يساوي `0`.

## مثال
`n = 7`: `7 % 2` يساوي `1` لا `0`، لذا اطبع `odd`.
`n = 0`: `0 % 2` يساوي `0`، لذا اطبع `even`. الصفر عدد زوجي.
`n = -3`: هنا تختلف اللغات. ~Python~ تعطي `-3 % 2 = 1`. أما ~Java~ و~C++~ و~JavaScript~ فتعطي `-1`.

## طريقة التفكير في الحل
يأتي الفرق من طريقة تقريب كل لغة لناتج القسمة. ~Python~ تقرّب `-3 / 2` إلى الأسفل، أي إلى `-2`، فيكون الباقي `1`: `-3 = -2 * 2 + 1`. أما ~Java~ و~C++~ و~JavaScript~ فتقرّب نحو الصفر، أي إلى `-1`، فيكون الباقي `-1`: `-3 = -1 * 2 - 1`.

تتفق القاعدتان على أمر واحد: العدد الزوجي يترك دائمًا الباقي `0`. لذا اختبر `n % 2 == 0` لمعرفة الزوجي، وكل ما عدا ذلك فردي. هذا يعمل مع الأعداد الموجبة والسالبة والصفر، في كل لغة.

## الـ~complexity~
الـ~time complexity~: `O(1)`، عملية قسمة واحدة.
الـ~space complexity~: `O(1)`.

## انتبه إلى
- اختبار `n % 2 == 1` لمعرفة الفردي. في ~Java~ و~C++~ و~JavaScript~، `-3 % 2` يساوي `-1`، فيظهر `-3` زوجيًا. في ~Python~ ينجح الاختبار نفسه مصادفة.
- حجم `n`. القيمة `1,000,000,000` تتسع في `int` بحجم ~32-bit~، وأكبر قيمة فيه `2,147,483,647`، لذا يكفي `int` هنا.
- الكلمتان بالضبط: `even` و`odd`، بأحرف صغيرة.

نظام التصحيح لا يرى إلا الكلمة، فلا يمكنه معرفة هل استخدمت `%` أم حيلة أخرى. استخدم `%`: فهو يتكرر في كل المسائل تقريبًا.
TEXT,
    ],
    'fizzbuzz' => [
        'title' => 'FizzBuzz',
        'body' => <<<'TEXT'
الـ~loop~ يكرر جزءًا من الكود. الـ~for loop~ ينفّذه مرة لكل قيمة يأخذها الـ~counter~. في ~Python~، يعطي `for i in range(1, n + 1)` القيم `i = 1, 2, ..., n`. وفي ~Java~ و~C++~ و~JavaScript~ يفعل `for (i = 1; i <= n; i++)` الشيء نفسه.

يكون العدد من مضاعفات `3` عندما لا تترك قسمته على `3` أي باقٍ: `i % 3 == 0`. والأمر نفسه مع `5`.

## مثال
خذ `n = 15`. الأسطر هي:
`1, 2, Fizz, 4, Buzz, Fizz, 7, 8, Fizz, Buzz, 11, Fizz, 13, 14, FizzBuzz`.
`3` و`6` و`9` و`12` من مضاعفات `3`. و`5` و`10` من مضاعفات `5`. و`15` من مضاعفات الاثنين، لذا يأخذ `FizzBuzz`.

## طريقة التفكير في الحل
داخل الـ~loop~، استخدم سلسلة `if` و`else if` و`else`. يفحص الكمبيوتر الـ~conditions~ من الأعلى، وينفّذ فقط أول واحد منها يكون صحيحًا.

لهذا تأتي حالة `FizzBuzz` أولًا. العدد الذي هو من مضاعفات `3` و`5` معًا هو أيضًا من مضاعفات `3`. إذا جاء فحص `3` أولًا، فإن `15` يطابقه ويطبع `Fizz`، ولا يُوصل إلى سطر `FizzBuzz` أبدًا. لذا افحص `i % 15 == 0` أولًا: مضاعفات `3` و`5` معًا هي بالضبط مضاعفات `15`. ثم افحص `3`، ثم `5`، وإلا فاطبع `i`.

وهناك طريقة أخرى تعمل أيضًا: ابدأ بكلمة فارغة، وأضف `Fizz` لمضاعفات `3`، وأضف `Buzz` لمضاعفات `5`، واطبع `i` إذا بقيت الكلمة فارغة.

## الـ~complexity~
الـ~time complexity~: `O(n)`، ~single pass~ على الأعداد.
الـ~space complexity~: `O(1)` إلى جانب الـ~output~.

## انتبه إلى
- المدى. ابدأ من `1` وأدرج `n` نفسه: `range(1, n)` يتوقف عند `n - 1`.
- إجابة واحدة في كل سطر، مكتوبة هكذا بالضبط: `Fizz` و`Buzz` و`FizzBuzz`.
- `n = 1`. السطر الوحيد هو `1`.

نظام التصحيح لا يقارن إلا الأسطر، فلا يمكنه معرفة أي الطريقتين استخدمت. جرّب `n = 15` أولًا وتأكد أن السطر الأخير هو `FizzBuzz`.
TEXT,
    ],
    'count-vowels' => [
        'title' => 'عدّ حروف العلة',
        'body' => <<<'TEXT'
الـ~string~ قطعة من النص، والـ~character~ حرف أو رقم أو مسافة واحدة فيه. الـ~input~ هنا سطر كامل وقد يحتوي على مسافات، لذا اقرأ السطر كله لا كلمة واحدة.
- ~Python~: `input()` يقرأ السطر كله.
- ~Java~: `nextLine()` من `Scanner` أو `readLine()` من `BufferedReader`. أما `next()` فيتوقف عند أول مسافة.
- ~C++~: `getline(cin, s)`. أما `cin >> s` فيتوقف عند أول مسافة.
- ~JavaScript~: اقرأ الـ~standard input~ كله وخذ السطر الأول.

## مثال
خذ السطر `Programming  Is   FUN`.
بالأحرف الصغيرة يصبح `programming  is   fun`.
مُرّ عليه ~character~ واحدًا في كل مرة. حروف العلة هي `o` و`a` و`i` في `programming`، و`i` في `is`، و`u` في `fun`. اطبع `5`.
السطر الفارغ، أو السطر المكوّن من مسافات فقط، لا يحتوي على حروف علة، لذا اطبع `0`.

## طريقة التفكير في الحل
استخدم ~loop~ على الـ~characters~: `for ch in s` في ~Python~، أو ~index~ من `0` إلى الطول ناقص `1` في اللغات الأخرى. قارن كل ~character~ مع `a` و`e` و`i` و`o` و`u`، وأضف `1` إلى `count` عند التطابق.

تُقارن الـ~characters~ بدقة: `A` و`a` مختلفان. لذا حوّل كل ~character~ إلى حرف صغير أولًا، باستخدام `lower()` في ~Python~، و`Character.toLowerCase` في ~Java~، و`tolower` في ~C++~، و`toLowerCase()` في ~JavaScript~. عندها تكفي خمس مقارنات للحالتين.

## الـ~complexity~
الـ~time complexity~: `O(n)`، حيث `n` طول السطر، نظرة واحدة إلى كل ~character~.
الـ~space complexity~: `O(n)` لتخزين السطر.

## انتبه إلى
- قراءة كلمة واحدة. مع `Programming  Is   FUN`، يقرأ `cin >> s` أو `next()` كلمة `Programming` فقط ويطبع `3`.
- الأحرف الكبيرة. دون التحويل إلى أحرف صغيرة، يضيع `I` و`U`، وهذا يعطي `3` أيضًا.
- السطر الفارغ. الإجابة `0`، وليست خطأ.

نظام التصحيح لا يرى إلا العدد، لذا يجتاز الاختبارات أيضًا حل يستخدم ~regular expression~ أو ~built-in function~ للعد. اكتب الـ~loop~: إنه النمط الذي تقوم عليه معظم مسائل الـ~strings~.
TEXT,
    ],
    'factorial' => [
        'title' => 'Factorial',
        'body' => <<<'TEXT'
الـ~factorial~ للعدد `n`، ويُكتب `n!`، هو `1 * 2 * 3 * ... * n`. و`0!` يساوي `1`.

الـ~function~ جزء من الكود له اسم ويمكن استدعاؤه. الـ~parameter~ هو القيمة التي تُمرَّر إليه، والـ~return value~ هو ما يعيده. هنا، `factorial(n)` يُرجع `n!`.

## مثال
`5! = 1 * 2 * 3 * 4 * 5 = 120`.
لحساب `0!`، ابدأ من `result = 1`. لا يجد الـ~loop~ ما يضربه، فتبقى `result` تساوي `1`.
`12! = 479001600`، و`13! = 6227020800`، و`20! = 2432902008176640000`.

## طريقة التفكير في الحل
باستخدام ~loop~: اجعل `result = 1`، واضربه في كل `i` من `2` إلى `n`، ثم أرجِعه.
باستخدام ~recursion~، يستدعي الـ~function~ نفسه: `factorial(n) = n * factorial(n - 1)`، و`factorial(0) = 1` هي نقطة التوقف.

الجزء الصعب هو الحجم. لكل ~type~ رقمي قيمة قصوى، وتجاوزها يسمى ~overflow~.
- `int` بحجم ~32-bit~، وهو الافتراضي في ~Java~ و~C++~، يتسع حتى `2,147,483,647`. يتسع فيه `12!`، أما `13!` فلا. في ~Java~ تلتف القيمة بصمت ويُطبع `1932053504`؛ وفي ~C++~ تكون النتيجة ~undefined~، وهنا طُبعت القيمة نفسها.
- `long` في ~Java~ و`long long` في ~C++~ يتسعان حتى نحو `9.2 * 10^18`، لذا يتسع `20!`.
- الأعداد الصحيحة في ~Python~ تكبر حسب الحاجة.
- `Number` في ~JavaScript~ يخزن الأعداد الصحيحة بدقة حتى `9,007,199,254,740,991` فقط. و`19!` أكبر من ذلك، لذا قد تُقرَّب العمليات الحسابية دون تحذير. استخدم `BigInt` بدلًا منه: اكتب `1n`، وحوّل باستخدام `BigInt(n)`، ولن يحدث تقريب أبدًا.

## الـ~complexity~
الـ~time complexity~: `O(n)` عملية ضرب.
الـ~space complexity~: `O(1)` مع الـ~loop~، و`O(n)` مع استدعاءات الـ~recursion~.

## انتبه إلى
- `int` بدلًا من `long`. `13` هو أول ~input~ يجعله يفشل.
- `Number` العادي في ~JavaScript~. حتى `21!` تخرج النتائج صحيحة مصادفة، لذا لا تستطيع الاختبارات كشف المشكلة. استخدم `BigInt` لتكون الإجابة صحيحة عن قصد، لا مصادفة.

لا يستطيع نظام التصحيح معرفة هل كتبت ~function~ أم ~loop~ أم ~recursion~، وجدول يحوي الإجابات وعددها `21` سيجتاز الاختبارات أيضًا. اكتب الـ~function~: الدروس التالية تبني عليه.
TEXT,
    ],
    'caesar-cipher' => [
        'title' => 'Caesar cipher',
        'body' => <<<'TEXT'
الـ~string~ سلسلة من الـ~characters~، لكل منها موضع يبدأ من `0` ورمز رقمي: رمز `a` هو `97`، ورمز `A` هو `65`، وتتبع بقية حروف كل أبجدية بالترتيب.

في ~Python~ و~Java~ و~JavaScript~، الـ~strings~ ~immutable~: كل تغيير ينشئ ~string~ جديدًا. ابنِ النتيجة بجمع الأجزاء ثم ربطها مرة واحدة، أو باستخدام ~builder~. أما في ~C++~ فالـ~strings~ قابلة للتغيير، لذا يضيف `+=` إلى نهايتها.

## مثال
خذ `k = 3` و`Hello, World!`. ينتقل `H` ثلاثة أحرف إلى الأمام فيصبح `K`، و`e` يصبح `h`، و`l` يصبح `o`. تبقى الفاصلة والمسافة و`!` كما هي. اطبع `Khoor, Zruog!`.
مع `k = 1`، يعود `z` إلى `a` ويعود `Z` إلى `A`. ومع `k = 1000`، لا يهم إلا `1000 % 26 = 12`، لذا يصبح `z` هو `l`.

## طريقة التفكير في الحل
للحرف الصغير أو الكبير، حوّله إلى عدد من `0` إلى `25`، وأضف `k`، وخذ باقي القسمة على `26`، ثم أعده حرفًا: `(c - 'a' + k) % 26 + 'a'`. باقي القسمة يعيد `z` إلى `a`، والأساس الصحيح، `'a'` أو `'A'`، يحافظ على حالة الحرف. انسخ كل ما عدا ذلك دون تغيير.

## ~Python~
    s = "Hello, World"
    print(s[0:5], s.lower())         # Hello hello, world
    print("-".join(s.split(", ")))   # Hello-World
    out = []
    for ch in s:
        out.append(chr(ord(ch) + 1) if ch.isalpha() else ch)
    print("".join(out))              # Ifmmp, Xpsme

## ~Java~
    String s = "Hello, World";
    System.out.println(s.substring(0, 5) + " " + s.toLowerCase());  // Hello hello, world
    System.out.println(String.join("-", s.split(", ")));            // Hello-World
    StringBuilder out = new StringBuilder();
    for (char ch : s.toCharArray()) out.append(Character.isLetter(ch) ? (char) (ch + 1) : ch);
    System.out.println(out);                                        // Ifmmp, Xpsme

## ~JavaScript~
    const s = 'Hello, World'
    console.log(s.slice(0, 5), s.toLowerCase())  // Hello hello, world
    console.log(s.split(', ').join('-'))         // Hello-World
    let out = ''
    for (const ch of s) out += /[a-z]/i.test(ch) ? String.fromCharCode(ch.charCodeAt(0) + 1) : ch
    console.log(out)                             // Ifmmp, Xpsme

## ~C++~
    string s = "Hello, World";
    cout << s.substr(0, 5) << "\n";  // Hello (substr takes a start and a length)
    string out;
    for (char ch : s) out += isalpha(ch) ? char(ch + 1) : ch;
    cout << out << "\n";             // Ifmmp, Xpsme

لا يوجد `split` في ~C++~؛ استخدم `stringstream`.

## الـ~complexity~
الـ~time complexity~: `O(n)`، حيث `n` طول النص، خطوة واحدة لكل ~character~.
الـ~space complexity~: `O(n)` للنتيجة.

## انتبه إلى
- صغّر `k` أولًا. إضافة `1000` إلى رمز ~character~ تتجاوز `z` بكثير.
- قراءة النص. قد يكون فارغًا ويحتوي على مسافات، لذا اقرأ السطر الثاني كله.
- `s += ch` داخل ~loop~ في ~Java~ ينسخ الـ~string~ في كل مرة. أما `StringBuilder` فلا.

نظام التصحيح لا يرى إلا الـ~output~، لذا تجتاز الاختبارات أيضًا نسخة مزاحة من الأبجدية تُستخدم بوصفها ~lookup table~.
TEXT,
    ],
    'diagonal-sums' => [
        'title' => 'مجموعا القطرين',
        'body' => <<<'TEXT'
الـ~list~ يحفظ القيم بالترتيب، كل قيمة عند ~index~ يبدأ من `0`؛ وتسميه ~Java~ و~C++~ و~JavaScript~ ~array~. الـ~2D list~ هو ~list~ من الصفوف: `grid[r][c]` هي القيمة في الصف `r` والعمود `c`.

## مثال
مع `n = 3`:
    1 2 3
    4 5 6
    7 8 9
القطر الرئيسي، من أعلى اليسار إلى أسفل اليمين: `grid[0][0] + grid[1][1] + grid[2][2] = 1 + 5 + 9 = 15`.
القطر الثانوي، من أعلى اليمين إلى أسفل اليسار: `grid[0][2] + grid[1][1] + grid[2][0] = 3 + 5 + 7 = 15`.
المركز، `5`، يُحسب في المجموعين. اطبع `15 15`.

## طريقة التفكير في الحل
اقرأ `n`، ثم اقرأ كل صف في ~list~ خاص به. الـ~nested loops~ تزور كل خلية: ~loop~ خارجي على الصفوف، و~loop~ داخلي على الأعمدة. أما القطران فيكفيهما ~loop~ واحد: الصف `i` يلتقي بالقطر الرئيسي عند العمود `i`، وبالقطر الثانوي عند العمود `n - 1 - i`.

مع قيم يصل حجمها إلى `1,000,000,000` و`n` حتى `50`، قد يصل المجموع إلى `50,000,000,000`. استخدم `long` في ~Java~ و`long long` في ~C++~.

## ~Python~
    n = 3
    grid = [[0] * n for _ in range(n)]  # n separate rows
    trap = [[0] * n] * n                # n references to ONE row
    grid[0][0] = 1
    trap[0][0] = 1
    print(grid)  # [[1, 0, 0], [0, 0, 0], [0, 0, 0]]
    print(trap)  # [[1, 0, 0], [1, 0, 0], [1, 0, 0]]

## ~Java~
    int n = 3;
    long[][] grid = new long[n][n];   // every cell starts at 0
    for (int r = 0; r < n; r++)
        for (int c = 0; c < n; c++)
            grid[r][c] = r * n + c + 1;  // 1 to 9, row by row
    System.out.println(grid[1][2]);   // 6

## ~JavaScript~
    const n = 3
    const grid = Array.from({ length: n }, () => new Array(n).fill(0))  // n rows
    const trap = new Array(n).fill(new Array(n).fill(0))                // one shared row
    grid[0][0] = 1
    trap[0][0] = 1
    console.log(grid[1][0], trap[1][0])  // 0 1

## ~C++~
    int n = 3;
    vector<vector<long long>> grid(n, vector<long long>(n, 0));  // n copies of a row
    grid[0][0] = 1;
    cout << grid[1][0] << "\n";  // 0

## الـ~complexity~
الـ~time complexity~: `O(n^2)` لقراءة الشبكة، ثم `O(n)` للمجموعين.
الـ~space complexity~: `O(n^2)` لتخزين الشبكة.

## انتبه إلى
- الصف المشترك. `[[0] * n] * n` في ~Python~ و`fill` في ~JavaScript~ يضعان الصف نفسه في كل خانة، لذا يؤدي تغيير خلية إلى تغيير عمودها كله. أما ~C++~ فتنسخ الصف.
- عندما يكون `n` فرديًا، ينتمي المركز إلى القطرين معًا؛ لا تطرحه.
- المجاميع بحجم ~32-bit~ تلتف مع أكبر القيم.

الجمع أثناء القراءة دون تخزين الشبكة يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يرى إلا عددين.
TEXT,
    ],
    'word-frequency' => [
        'title' => 'تكرار الكلمات',
        'body' => <<<'TEXT'
الـ~dictionary~، أو الـ~map~، يخزن أزواجًا: كل ~key~ يقود إلى قيمة. هنا الـ~key~ هو الكلمة، والقيمة هي عدد مرات ظهورها. تسميه ~Python~ `dict`، و~Java~ `HashMap`، و~JavaScript~ `Map`، و~C++~ `map` أو `unordered_map`. البحث عن ~key~ في ~hash map~ يستغرق وقتًا `O(1)` في المتوسط.

## مثال
خذ `the cat and the hat`.
الكلمة `the` ليست في الـ~map~ بعد، لذا تأخذ `1`. ثم `cat 1`، و`and 1`، ثم `the` مرة أخرى `2`، و`hat 1`.
رتّب حسب العدد، الأكبر أولًا، وأبجديًا عند تساوي العدد:
`the 2, and 1, cat 1, hat 1`.

## طريقة التفكير في الحل
العدّ يحتاج إلى ~lookup~ مع قيمة افتراضية: الكلمة التي لم تظهر بعد تُعدّ `0`. أضف `1` وخزّن النتيجة من جديد. ثم مُرّ على الـ~entries~، وانسخها إلى ~list~ ورتّبه.

لا تطبع الـ~map~ بترتيبه الخاص. `dict` في ~Python~ و`Map` في ~JavaScript~ يحافظان على ترتيب إضافة الـ~keys~، و`HashMap` في ~Java~ ليس له ترتيب مفيد، و`map` في ~C++~ مرتب حسب الـ~key~. لا شيء من ذلك يضع الأكثر تكرارًا أولًا.

## ~Python~
    counts = {}
    for w in "the cat and the hat".split():
        counts[w] = counts.get(w, 0) + 1  # get with a default
    for w, c in sorted(counts.items(), key=lambda item: (-item[1], item[0])):
        print(w, c)  # the 2, and 1, cat 1, hat 1

## ~Java~
    Map<String, Integer> counts = new HashMap<>();
    for (String w : "the cat and the hat".split(" "))
        counts.put(w, counts.getOrDefault(w, 0) + 1);
    System.out.println(counts.get("the"));  // 2
    for (Map.Entry<String, Integer> e : counts.entrySet())
        System.out.println(e.getKey() + " " + e.getValue());  // no useful order

## ~JavaScript~
    const counts = new Map()
    for (const w of 'the cat and the hat'.split(' '))
      counts.set(w, (counts.get(w) ?? 0) + 1)  // get gives undefined, ?? turns it into 0
    for (const [w, c] of counts) console.log(w, c)  // the 2, cat 1, and 1, hat 1

## ~C++~
    map<string, int> counts;
    istringstream words("the cat and the hat");
    string w;
    while (words >> w) counts[w]++;  // a missing key starts at 0
    for (const auto& [word, c] : counts) cout << word << " " << c << "\n";  // and, cat, hat, the

## الـ~complexity~
الـ~time complexity~: `O(w)` لعدّ `w` كلمة، إضافة إلى `O(d log d)` لترتيب الكلمات المختلفة وعددها `d`.
الـ~space complexity~: `O(d)` لتخزين الـ~map~.

## انتبه إلى
- القراءة في ~C++~. `counts["dog"]` يضيف `dog` بقيمة `0` حتى لو كان القصد مجرد النظر. استخدم `counts.count(w)` للتحقق.
- التعادل. عند تساوي العدد، تُرتب الكلمات أبجديًا.

ترتيب كل الكلمات ثم عدّ المتتاليات المتساوية منها يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يستطيع معرفة هل استخدمت ~map~.
TEXT,
    ],
    'sort-the-leaderboard' => [
        'title' => 'ترتيب لوحة الصدارة',
        'body' => <<<'TEXT'
الـ~sorting~ باستخدام ~key~ يعني مقارنة قيمة محسوبة من كل عنصر بدلًا من العنصر كله. مع عدة ~keys~، قارن بالأول؛ ولا تنظر إلى الثاني إلا عند تساوي الأول. للترتيب التنازلي، اعكس ذلك الـ~key~ وحده، لا الباقي.

يكون الـ~sort~ ~stable~ عندما تحافظ العناصر المتساوية في المقارنة على ترتيبها الحالي. `sort` في ~Python~ و`List.sort` في ~Java~ و`sort` في ~JavaScript~ كلها ~stable~؛ أما `sort` في ~C++~ فليس كذلك، لكن `stable_sort` كذلك.

## مثال
`alice 50, bob 70, carol 50, dave 70`.
حسب الدرجة، الأعلى أولًا، تأتي درجات `70` قبل درجات `50`. ضمن `70` يأتي `bob` قبل `dave`؛ وضمن `50` يأتي `alice` قبل `carol`.
اطبع `bob 70, dave 70, alice 50, carol 50`.

## طريقة التفكير في الحل
إحدى الطرق مقارنة واحدة تفحص الدرجة، الأعلى أولًا، ثم الاسم. والأخرى ~two passes~ باستخدام ~stable sort~: رتّب حسب الاسم، ثم رتّب ترتيبًا ~stable~ حسب الدرجة، الأعلى أولًا. تبقى الأسماء مرتبة ضمن كل درجة.

في ~Python~، `reverse=True` يعكس الـ~key~ كله، بما في ذلك الأسماء. اجعل العدد سالبًا بدلًا من ذلك: `(-score, name)`.

الـ~comparator~ يأخذ عنصرين `a` و`b` ويحدد أيهما يأتي أولًا: في ~Java~ و~JavaScript~ العدد السالب يعني `a`، والموجب يعني `b`، و`0` يعني أنهما متساويان. وفي ~C++~ يُرجع `true` عندما يجب أن يأتي `a` قبل `b` بشكل صارم.

## ~Python~
    players = [("alice", 50), ("bob", 70), ("carol", 50), ("dave", 70)]
    players.sort(key=lambda p: (-p[1], p[0]))
    print(players)  # [('bob', 70), ('dave', 70), ('alice', 50), ('carol', 50)]

## ~Java~
    record Player(String name, int score) {}
    players.sort(Comparator.comparingInt(Player::score).reversed()
            .thenComparing(Player::name));

## ~JavaScript~
    players.sort((a, b) => b.score - a.score || (a.name < b.name ? -1 : a.name > b.name ? 1 : 0))

## ~C++~
    sort(players.begin(), players.end(), [](const Player& a, const Player& b) {
        if (a.score != b.score) return a.score > b.score;  // higher score first
        return a.name < b.name;
    });

## الـ~complexity~
الـ~time complexity~: `O(n log n)` مقارنة.
الـ~space complexity~: `O(n)` لتخزين الـ~list~.

## انتبه إلى
- ترتيب الأعداد في ~JavaScript~ دون ~comparator~: `[10, 9, 1].sort()` يقارنها كنص ويعطي `1, 10, 9`.
- إرجاع `a.score >= b.score` في ~comparator~ بلغة ~C++~. يجب أن يُرجع `false` للعناصر المتساوية، وإلا فقد يتصرف `sort` بشكل خاطئ.
- الـ~two passes~ في ~C++~ تحتاج إلى `stable_sort`.

نظام التصحيح لا يرى إلا الترتيب النهائي، فلا يمكنه التمييز بين مقارنة واحدة و~two passes~.
TEXT,
    ],
];
